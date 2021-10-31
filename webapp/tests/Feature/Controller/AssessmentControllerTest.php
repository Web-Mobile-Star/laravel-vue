<?php
/**
 * Copyright 2020-2021 Aston University
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Tests\Feature\Controller;

use App\Assessment;
use App\AssessmentSubmission;
use App\AssessmentTest;
use App\BuildResultFile;
use App\FileOverride;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\SubmissionsTable;
use App\Jobs\MarkSubmissionJob;
use App\Jobs\MavenBuildJob;
use App\JUnit\JUnitTestCase;
use App\ModelSolution;
use App\Policies\AssessmentPolicy;
use App\Policies\AssessmentSubmissionPolicy;
use App\Policies\TeachingModuleItemPolicy;
use App\Policies\TeachingModuleUserPolicy;
use App\Policies\ZipSubmissionPolicy;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\User;
use App\Zip\ExtendedZipArchive;
use App\ZipSubmission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\TMURoleSwitchingTestCase;
use ZipArchive;

class AssessmentControllerTest extends TMURoleSwitchingTestCase
{
    use WithFaker;

    /** @var int Expected number of passed tests in the BadBehaviourTest test class in test-resources/java-policy. */
    const BAD_BEHAVIOUR_TEST_PASSED = 6;

    /** @var int Expected number of ignored tests in the BadBehaviourTest test class in test-resources/java-policy. */
    const BAD_BEHAVIOUR_TEST_IGNORED = 2;

    public function testShowModelSolutionNotDefinedYet() {
        /** @var Assessment $assessment */
        $this->setUpAssessment($item, $assessment, $tmu);
        $assessment->modelSolutions()->delete();
        $assessment->refresh();

        $tmu->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);
        $response = $this->actingAs($tmu->user)->get($this->getRouteForShow($item, $assessment));
        $response->assertSuccessful();
        $response->assertDontSee('Available model solutions');
    }

    public function testShowModelSolutionRoles()
    {
        $this->setUpAssessment($item, $assessment, $tmu);

        $request = function () use ($tmu, $item, $assessment) {
            return $this->actingAs($tmu->user)->get($this->getRouteForShow($item, $assessment));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
        ], [
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request,200);
    }

    public function testAuthorizeNesting() {
        $this->setUpAssessment($item, $assessment, $tmu);
        $this->setUpAssessment($item2, $assessment2, $tmu2);

        // Should be forbidden to try to do /modules/X/assessments/Y if Y does not belong to module X
        $response = $this->actingAs($tmu->user)->get($this->getRouteForShow($item2, $assessment));
        $response->assertForbidden();
    }

    public function testStoreModelSolutionRoles() {
        $this->setUpAssessment($item, $assessment, $tmu);

        // We don't want a job to actually run (too slow) - we just want to check the authorization
        $doUpload = function() use ($tmu, $item, $assessment) {
            return $this->actingAs($tmu->user)->post($this->getRouteForStore($item, $assessment));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $doUpload, 302, false);
    }

    public function testStoreModelSolution() {
        Storage::fake('local');

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->user->givePermissionTo(AssessmentPolicy::UPLOAD_MODEL_SOLUTION_PERMISSION);

        $zipPath = 'test-resources/java-policy.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $doUpload = function() use ($tmu, $item, $assessment, $zipPath) {
            return $this->actingAs($tmu->user)->post($this->getRouteForStore($item, $assessment), [
                'jobfile' => UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath))
            ]);
        };

        $response = $doUpload();
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $assessment->refresh();
        $this->assertNotNull($assessment->latestModelSolution->submission);
        $this->assertNotEquals(ZipSubmission::SHA256_PENDING, $assessment->latestModelSolution->submission->sha256);

        // Check that assessment tests have been created
        $this->assertTrue($assessment->tests()->count() > 0);

        // Do it again (replace)
        $oldModelSolution = $assessment->latestModelSolution;
        $oldSubmission = $oldModelSolution->submission;
        $response = $doUpload();
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $assessment->refresh();
        $this->assertEquals($oldModelSolution->version + 1, $assessment->latestModelSolution->version);
        $this->assertNotEquals($oldSubmission->id, $assessment->latestModelSolution->zip_submission_id);

        // Check that assessment tests have been kept
        $this->assertEquals(self::BAD_BEHAVIOUR_TEST_PASSED + self::BAD_BEHAVIOUR_TEST_IGNORED, $assessment->tests()->count());
    }

    public function testDeleteModelSolution() {
        Storage::fake('local');

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);

        $request = function () use ($assessment, $tmu) {
            return $this->actingAs($tmu->user)->delete(route('modules.assessments.destroyModelSolution', [
                'module' => $tmu->teaching_module_id, 'assessment' => $assessment->id
            ]));
        };

        // Without the permission, you cannot delete it
        $modelSolution = $assessment->latestModelSolution;
        $response = $request();
        $response->assertForbidden();

        // With the permission, it's OK
        $tmu->user->givePermissionTo(AssessmentPolicy::DELETE_MODEL_SOLUTION_PERMISSION);
        $response = $request();
        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDeleted($modelSolution);
    }

    public function testShowOverridesNoModelSolution() {
        Storage::fake('local');

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();

        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        $assessment->usage()->save($item);
        $assessment->latestModelSolution->delete();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $item->teaching_module_id
        ]);
        $tmu->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        $response = $this->actingAs($tmu->user)->get(
            route('modules.assessments.showOverrides', [
                'module' => $tmu->teaching_module_id,
                'assessment' => $assessment->id,
            ]));

        $response->assertSuccessful();
        $response->assertSee("No model solution has been provided yet");
    }

    public function testShowOverridesRoles() {
        Storage::fake('local');

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);

        $request = function() use ($tmu, $assessment) {
            return $this->actingAs($tmu->user)->get(
                route('modules.assessments.showOverrides', [
                    'module' => $tmu->teaching_module_id,
                    'assessment' => $assessment->id,
                ]));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
        ], [
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testStoreOverridesTutor() {
        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         */
        $this->setUpStoreOverrides($item, $assessment, $tmu, $paths);
        $tmu->user->givePermissionTo(AssessmentPolicy::VIEW_OVERRIDES_PERMISSION);
        $tmu->user->givePermissionTo(AssessmentPolicy::MODIFY_OVERRIDES_PERMISSION);

        $doRequest = function($selected) use ($tmu, $assessment) {
            $response = $this->storeOverrides($tmu, $assessment, $selected);
            $response->assertRedirect();
            $response->assertSessionHasNoErrors();
            $response->assertSessionHas('status');

            $actual = $assessment->fileOverrides()->pluck('path')->toArray();
            sort($selected);
            sort($actual);
            $this->assertEquals($selected, $actual);
        };

        // Empty set
        $doRequest([]);
        // Add several
        $doRequest([$paths[0], $paths[2]]);
        // Swap one for another
        $doRequest([$paths[1], $paths[2]]);
    }

    public function testStoreOverridesRoles() {
        $this->setUpStoreOverrides($item, $assessment, $tmu, $paths);
        $selectedPaths = [$paths[0], $paths[3]];
        $request = function() use ($tmu, $assessment, $selectedPaths) {
            return $this->storeOverrides($tmu, $assessment, $selectedPaths);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request);
    }

    /**
     * @param $item
     * @param $assessment
     * @param $tmu
     */
    private function setUpAssessment(&$item, &$assessment, &$tmu): void
    {
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();

        /** @var ModelSolution $modelSolution */
        $assessment = Assessment::factory()->create();
        $assessment->usage()->save($item);

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $item->teaching_module_id
        ]);
    }

    public function testShowTestsDirectPermission() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);

        $tmu->givePermissionTo(AssessmentPolicy::VIEW_TESTS_PERMISSION);
        $response =  $this->actingAs($tmu->user)->get(route(
            'modules.assessments.showTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ));

        $response->assertSuccessful();
        $response->assertViewIs('assessments.showTests');
        $response->assertViewHas('tests');
        $this->assertCount(count($tests), $response->viewData('tests'));
    }

    public function testShowTestsRoles() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);

        $request = function () use ($tmu, $assessment) {
            return $this->actingAs($tmu->user)->get(route(
                'modules.assessments.showTests',
                ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
            ));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
        ], [
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testEditDirectPermission() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        $response =  $this->actingAs($tmu->user)->get(route(
            'modules.assessments.editTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ));

        $response->assertSuccessful();
        $response->assertViewIs('assessments.editTests');
        $response->assertViewHas('tests');
        $this->assertCount(count($tests), $response->viewData('tests'));
    }

    public function testEditTestsRoles() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);

        $request = function () use ($tmu, $assessment) {
            return $this->actingAs($tmu->user)->get(route(
                'modules.assessments.editTests',
                ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
            ));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testStoreTestsDirectPermission() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        $response = $this->actingAs($tmu->user)->post(route(
                'modules.assessments.storeTests',
                ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
            ), [
                'id' => $tests->pluck('id')->toArray(),
                'points' => ['1', '10', '5', '2', '3'],
                'feedback' => ['a', 'b', 'c', 'd', 'e'],
                'tasks' => [null, '01. First Step', '01. First Step', '02. Second Step', '02. Second Step']
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tests[0]->refresh();
        $this->assertEquals('1.00', $tests[0]->points);
        $this->assertEquals('a', $tests[0]->feedback_markdown);
        $this->assertNull($tests[0]->task);

        $tests[1]->refresh();
        $this->assertEquals('01. First Step', $tests[1]->task);
    }

    public function testStoreTestsNoChangesToMarksDoesNotScheduleReMarking() {
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var AssessmentTest[] $tests
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);
        AssessmentSubmission::factory()->create([
            'teaching_module_user_id' => $tmu->id,
            'assessment_id' => $assessment->id,
        ]);

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id]
        ), [
            'id' => $tests->pluck('id')->toArray(),
            'points' => [$tests[0]->points, $tests[1]->points, $tests[2]->points, $tests[3]->points, $tests[4]->points],
            'feedback' => ['a', 'b', 'c', 'd', 'e'],
            'tasks' => [null, null, null, null, null],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $tests[0]->refresh();
        $this->assertEquals('a', $tests[0]->feedback_markdown);
        Queue::assertNothingPushed();
    }

    public function testStoreTestsChangesToMarksSchedulesReMarking() {
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var AssessmentTest[] $tests
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create([
            'teaching_module_user_id' => $tmu->id,
            'assessment_id' => $assessment->id,
        ]);

        // Make sure there will be *some* change to the marks in the assessment
        $tests[0]->points = '5';
        $tests[0]->save();

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id]
        ), [
            'id' => $tests->pluck('id')->toArray(),
            'points' => ['10', '20', '30', '40', '50'],
            'feedback' => ['a', 'b', 'c', 'd', 'e'],
            'tasks' => [null, null, null, null, null],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushedOn(ZipSubmission::QUEUE_NON_JAVA,  MarkSubmissionJob::class,
            function (MarkSubmissionJob $job) use ($asub) {
                return $job->aSubmissionId === $asub->id;
            });
    }

    public function testStoreTestsNegativeScore() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ), [
            'id' => [$tests[0]->id],
            'points' => ['-1'],
            'feedback' => ['a'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('points.0');
    }

    public function testStoreTestsExcessiveScore() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ), [
            'id' => [$tests[0]->id],
            'points' => ['200'],
            'feedback' => ['a'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('points.0');
    }

    public function testStoreTestsTooManyPassed() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ), [
            'id' => [$tests[0]->id],
            'points' => ['0'],
            'feedback' => ['a', 'b'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('feedback');
    }

    public function testStoreTestsPassedTooLong() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ), [
            'id' => [$tests[0]->id],
            'points' => ['0'],
            'feedback' => [implode($this->faker->words(10000))],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('feedback.0');
    }

    public function testStoreTestsWithTestFromAnotherAssessment() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::MODIFY_TESTS_PERMISSION);

        /** @var AssessmentTest $otherTest */
        $otherTest = AssessmentTest::factory()->create();

        $response = $this->actingAs($tmu->user)->post(route(
            'modules.assessments.storeTests',
            ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id ]
        ), [
            'id' => [$tests[0]->id, $otherTest->id],
            'points' => ['0', '0'],
            'feedback' => ['a', 'x'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('id.1');

        $otherTest->refresh();
        $this->assertNotEquals('x', $otherTest->feedback_markdown);
    }

    public function testStoreTestsRoles() {
        $this->setUpAssessmentWithTests($assessment, $tests, $tmu);

        $request = function() use ($tmu, $assessment, $tests) {
            return $this->actingAs($tmu->user)->post(route(
                'modules.assessments.storeTests',
                ['module' => $assessment->usage->teaching_module_id, 'assessment' => $assessment->id]
            ), [
                'id' => $tests->pluck('id')->toArray(),
                'points' => ['1', '10', '5', '2', '3'],
                'feedback' => ['a', 'b', 'c', 'd', 'e'],
                'tasks' => [null, null, null, null, null],
            ]);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request);
    }

    public function testStoreSubmission() {
        Storage::fake('local');

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);

        // Replace the model solution
        $modelZipPath = 'test-resources/java-policy-model.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $modelZipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE, '', ['target']);
        $modelStoragePath = Storage::putFile('submissions', $modelZipPath);
        $assessment->latestModelSolution->submission->diskPath = $modelStoragePath;
        $assessment->latestModelSolution->submission->save();

        // Add an override with the test suite
        $fileOverride = new FileOverride();
        $fileOverride->path = 'src/test/java/uk/ac/aston/autofeedback/policy/BadBehaviourTest.java';
        $fileOverride->assessment_id = $assessment->id;
        $fileOverride->save();

        // Adding a test which *is* in the tests (shouldn't show up as missing)
        $existingTest = new AssessmentTest;
        $existingTest->name = 'allEnvironmentVariables';
        $existingTest->feedback_markdown = '';
        $existingTest->assessment_id = $assessment->id;
        $existingTest->points = 10;
        $existingTest->class_name = 'uk.ac.aston.autofeedback.policy.BadBehaviourTest';
        $existingTest->save();

        // Adding a test case which is not really in the tests (to check display/measurement of missing tests)
        $missingTest = new AssessmentTest;
        $missingTest->name = 'missingTest';
        $missingTest->feedback_markdown = 'missing test is missing';
        $missingTest->assessment_id = $assessment->id;
        $missingTest->points = 15;
        $missingTest->class_name = 'uk.ac.aston.this.DoesNotExist';
        $missingTest->save();

        // Zip up the submission (same as solution but without the test file, to test file overrides)
        $uploadedFile = $this->createSampleUploadedFile();

        // Generic request template
        $request = function ($values) use  ($tmu, $item, $assessment) {
            return $this->actingAs($tmu->user)->post(route('modules.assessments.storeSubmission', [
                'module' => $tmu->teaching_module_id,
                'assessment' => $assessment->id,
            ]), $values);
        };

        // Without permission to upload, it's forbidden
        $response = $request([]);
        $response->assertForbidden();
        $this->assertEquals(0, AssessmentSubmission::count());

        // Without permission to view, it's still forbidden
        $tmu->givePermissionTo(AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION);
        Auth::user()->moduleUser($tmu->module)->refresh();
        $response = $request([]);
        $response->assertForbidden();
        $this->assertEquals(0, AssessmentSubmission::count());

        // Without fields, it won't work
        $tmu->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);
        Auth::user()->moduleUser($tmu->module)->refresh();
        $response = $request([]);
        $response->assertRedirect()->assertSessionHasErrors(['jobfile', 'feedbackIntentUnderstood']);
        $this->assertEquals(0, AssessmentSubmission::count());

        // Without consent, it won't work
        $response = $request(['jobfile' => $uploadedFile]);
        $response->assertRedirect()->assertSessionHasErrors(['feedbackIntentUnderstood']);
        $this->assertEquals(0, AssessmentSubmission::count());

        // With both, it will work as expected
        $response = $request(['jobfile' => $uploadedFile, 'feedbackIntentUnderstood' => 1]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(1, AssessmentSubmission::count());

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::firstOrFail();
        $this->assertNotNull($asub->submission);
        $this->assertEquals($tmu->id, $asub->author->id);
        $this->assertEquals($assessment->id, $asub->assessment->id);
        $this->assertEquals(self::BAD_BEHAVIOUR_TEST_PASSED, $asub->passed);
        $this->assertEquals(self::BAD_BEHAVIOUR_TEST_IGNORED, $asub->skipped);
        $this->assertNotEquals(ZipSubmission::SHA256_PENDING, $asub->submission->sha256);
        $this->assertEquals($assessment->latestModelSolution->id, $asub->modelSolution->id);

        // Try showing the submission
        $response = $this->get(route('modules.items.show', [
            'module' => $tmu->teaching_module_id,
            'item' => $assessment->usage->id
        ]));

        $response->assertSuccessful();
        $response->assertSee('attempt #1');
        $response->assertSee(' 1 missing');
        $response->assertSee($missingTest->name);
        $response->assertSee('fa-question-circle');

        // Since there is a test missing, we should see a link to check the output to see if there were
        // compilation issues.
        $response->assertSee($asub->submission->stdoutResult()->url($asub->submission));

        // Try showing the basic progress chart, and the progress stats
        $chartRouteParams = [
            'module' => $tmu->teaching_module_id,
            'assessment' => $asub->assessment_id
        ];

        $passedClassName = 'uk.ac.aston.autofeedback.policy.BadBehaviourTest';
        $passedTestName = 'allEnvironmentVariables';
        $passedStatusParams =  [
            'module' => $tmu->teaching_module_id,
            'assessment' => $asub->assessment_id,
            'className' => $passedClassName,
            'testName' => $passedTestName,
            'status' => JUnitTestCase::STATUS_PASSED,
        ];

        // First, without the permission to view stats (shouldn't work)
        $response = $this->get(route('modules.assessments.viewProgressChart', $chartRouteParams));
        $response->assertForbidden();
        $response = $this->get(route('modules.assessments.jsonProgressChart', $chartRouteParams));
        $response->assertForbidden();
        $response = $this->get(route('modules.assessments.jsonClassTestStatus', $passedStatusParams));
        $response->assertForbidden();

        // Now, with the permission
        $tmu->givePermissionTo(AssessmentPolicy::VIEW_PROGRESS_PERMISSION);
        Auth::user()->moduleUser($tmu->module)->refresh();

        $response = $this->get(route('modules.assessments.viewProgressChart', $chartRouteParams));
        $response->assertSuccessful();

        // Check that the JSON follows the basic hierarchical format needed by D3
        $response = $this->get(route('modules.assessments.jsonProgressChart', $chartRouteParams));
        $response->assertSuccessful();
        $response->assertJson([
            'name' => 'Assessment',
            'children' => [
                [
                    'name' => $passedClassName,
                    'children' => [
                        [
                            'name' => $passedTestName,
                            'children' => [
                                ['name'=> 'passed', 'value' => 1],
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test for one passed submission
        $response = $this->get(route('modules.assessments.jsonClassTestStatus', $passedStatusParams));
        $response->assertJson([
            [
                'submission_id' => $asub->id,
                'user_id' => $tmu->user_id,
                'user_name' => $tmu->user->name,
            ]
        ]);

        // Test for failed submissions (there shouldn't be any)
        $failedStatusParams = $passedStatusParams;
        $failedStatusParams['status'] = JUnitTestCase::STATUS_FAILED;
        $response = $this->get(route('modules.assessments.jsonClassTestStatus', $failedStatusParams));
        $response->assertJsonCount(0);

        // Test for missing submissions where there aren't any
        $missingStatusParamsNoResults = $passedStatusParams;
        $missingStatusParamsNoResults['status'] = JUnitTestCase::STATUS_MISSING;
        $response = $this->get(route('modules.assessments.jsonClassTestStatus', $missingStatusParamsNoResults));
        $response->assertJsonCount(0);

        // Test for missing submissions where there is one
        $missingStatusParamsWithResults = $missingStatusParamsNoResults;
        $missingStatusParamsWithResults['className'] = $missingTest->class_name;
        $missingStatusParamsWithResults['testName'] = $missingTest->name;

        $response = $this->get(route('modules.assessments.jsonClassTestStatus', $missingStatusParamsWithResults));
        $response->assertJson([
            [
                'user_id' => $tmu->user_id,
                'user_name' => $tmu->user->name,
            ]
        ]);
    }

    public function testShowSubmissionLate() {
        Storage::fake('local');
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        $request = function() use ($user, $asub) {
            return $this->actingAs($user)->get(route('modules.submissions.show', [
                'module' => $asub->assessment->usage->teaching_module_id,
                'submission' => $asub->id,
            ]));
        };

        $response = $request();
        $response->assertSuccessful();
        $response->assertDontSee('>late<', false);

        $asub->assessment->due_by = Carbon::now()->add(-2, 'hour');
        $asub->assessment->save(['timestamps' => false]);

        $response = $request();
        $response->assertSuccessful();
        $response->assertSee('>late<', false);
    }

    public function testStoreSubmissionBeforeAvailableIsForbidden() {
        Storage::fake('local');
        Queue::fake();

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION);
        $tmu->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);

        // Suppose the item was only available from tomorrow...
        $item->available_from = new Carbon('tomorrow');
        $item->save();
        $uploadedFile = $this->createSampleUploadedFile();

        // Upload the submission
        $response = $this->actingAs($tmu->user)->post(route('modules.assessments.storeSubmission', [
                'module' => $tmu->teaching_module_id,
                'assessment' => $assessment->id
            ]), [
                'jobfile' => $uploadedFile,
                'feedbackIntentUnderstood' => 1,
            ]);

        // The submission should not go through (the assessment is not available)
        Queue::assertNothingPushed();
        $response->assertForbidden();
    }

    public function testStoreSubmissionWithSubfolder() {
        Storage::fake('local');

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);

        // Replace the model solution
        $modelZipPath = 'test-resources/java-policy-model.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $modelZipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE, '', ['target']);
        $modelStoragePath = Storage::putFile('submissions', $modelZipPath);
        $assessment->latestModelSolution->submission->diskPath = $modelStoragePath;
        $assessment->latestModelSolution->submission->save();

        // Override only the POM file (the "student" is the one providing the test)
        $fileOverride = new FileOverride();
        $fileOverride->path = "pom.xml";
        $fileOverride->assessment_id = $assessment->id;
        $fileOverride->save();

        // Zip up the submission (same as solution but without the test file, and in a subfolder
        // as most students using the web-based submission would do it).
        $zipPath = 'test-resources/java-policy.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE, 'subfolder', ['src/test/java', 'target']);
        $uploadedFile = UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath));

        // Generic request template
        $request = function ($values) use  ($tmu, $item, $assessment) {
            return $this->actingAs($tmu->user)->post(route('modules.assessments.storeSubmission', [
                'module' => $tmu->teaching_module_id,
                'assessment' => $assessment->id,
            ]), $values);
        };

        // Submission should go through and pass the tests
        $tmu->givePermissionTo(AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION);
        $tmu->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);
        $response = $request(['jobfile' => $uploadedFile, 'feedbackIntentUnderstood' => 1]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(1, AssessmentSubmission::count());

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::firstOrFail();
        $this->assertEquals(self::BAD_BEHAVIOUR_TEST_PASSED, $asub->passed);
        $this->assertEquals(self::BAD_BEHAVIOUR_TEST_IGNORED, $asub->skipped);
    }

    public function testStoreTwoAttemptsKeepTwo() {
        Config::set(Assessment::CONFIG_KEEP_LATEST_ATTEMPTS, 2);
        $this->setUpMultipleAttemptTest($item, $assessment, $tmu, $request);

        // Submit several attempts
        $count = 2;
        for ($i = 0; $i < $count; $i++) {
            $response = $request();
            $response->assertSessionDoesntHaveErrors();
        }

        // We should have both in the DB
        $this->assertEquals($count, $assessment->submissionsFor($tmu)->count(), "Last two attempts should be kept");
    }

    public function testStoreTwoAttemptsKeepOneSameScores() {
        Config::set(Assessment::CONFIG_KEEP_LATEST_ATTEMPTS, 1);
        $this->setUpMultipleAttemptTest($item, $assessment, $tmu, $request);

        // Submit several attempts
        $count = 2;
        for ($i = 0; $i < $count; $i++) {
            $response = $request();
            $response->assertSessionDoesntHaveErrors();
        }

        // We should have both in the DB
        $this->assertEquals(1, $assessment->submissionsFor($tmu)->count(), "One attempt should be kept");
        $this->assertEquals($count, $assessment->latestSubmissionFor($tmu)->first()->attempt, "Latest attempt should be kept");
    }

    public function testStoreThreeAttemptsKeepOneBestScoreInOldest() {
        Config::set(Assessment::CONFIG_KEEP_LATEST_ATTEMPTS, 1);
        $this->setUpMultipleAttemptTest($item, $assessment, $tmu, $request);

        // Submit first attempt and give it the highest score (to be kept)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();
        $attempt = $assessment->latestSubmissionFor($tmu)->first();
        $attempt->points = '10.00';
        $attempt->save();

        // Second attempt (to be discarded)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();

        // Third attempt (to be kept)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();

        // We should have the best and the latest in the DB
        $this->assertEquals([1, 3], $assessment->submissionsFor($tmu)->pluck('attempt')->toArray(), "The first and third attempts should be kept");
        $this->assertEquals(3, $assessment->latestSubmissionFor($tmu)->first()->attempt, "Latest attempt should be kept");
        $this->assertEquals([1, 3], $assessment->submissions()->pluck('attempt')->toArray());
        $this->assertEquals([3], $assessment->latestSubmissions()->pluck('attempt')->toArray());
    }

    public function testStoreFourAttemptsKeepBestBeforeDeadlineAndOverall() {
        // Keep the most recent attempt always
        Config::set(Assessment::CONFIG_KEEP_LATEST_ATTEMPTS, 1);

        /** @var Assessment $assessment */
        $this->setUpMultipleAttemptTest($item, $assessment, $tmu, $request);
        $assessment->due_by = Carbon::now();
        $assessment->save();

        // Submit best attempt before deadline (to be kept)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();
        /** @var AssessmentSubmission $attempt */
        $attempt = $assessment->latestSubmissionFor($tmu)->first();
        $attempt->created_at = $assessment->due_by->add(-1, 'hour');
        $attempt->points = '3.00';
        $attempt->save(['timestamps' => false]);

        // Second attempt (to be discarded)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();
        $attempt = $assessment->latestSubmissionFor($tmu)->first();
        $attempt->setCreatedAt($assessment->due_by->add(2, 'hour'));
        $attempt->points = '2.00';
        $attempt->save();

        // Third attempt (best score overall: to be kept)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();
        $attempt = $assessment->latestSubmissionFor($tmu)->first();
        $attempt->setCreatedAt($assessment->due_by->add(3, 'hour'));
        $attempt->points = '4.00';
        $attempt->save();

        // Fourth attempt (latest: to be kept)
        $response = $request();
        $response->assertSessionDoesntHaveErrors();
        $attempt = $assessment->latestSubmissionFor($tmu)->first();
        $attempt->setCreatedAt($assessment->due_by->add(4, 'hour'));
        $attempt->points = '1.00';
        $attempt->save();

        // We should have the best before the deadline, the best overall, and the latest in the DB
        $this->assertEquals([1, 3, 4], $assessment->submissionsFor($tmu)->pluck('attempt')->toArray(),
            "The best before deadline, best overall, and latest attempts should be kept");
    }

    public function testStoreTwoAttemptsKeepAll() {
        Config::set(Assessment::CONFIG_KEEP_LATEST_ATTEMPTS, 0);
        $this->setUpMultipleAttemptTest($item, $assessment, $tmu, $request);

        // Submit several attempts
        $count = 2;
        for ($i = 0; $i < $count; $i++) {
            $response = $request();
            $response->assertSessionDoesntHaveErrors();
        }

        // We should have both in the DB
        $this->assertEquals($count, $assessment->submissionsFor($tmu)->count(), "All attempts should be kept");
    }

    public function testShowTestOutput() {
        Storage::fake('local');

        /** @var AssessmentSubmission $submission */
        $submission = AssessmentSubmission::factory()->create();
        $submission->author->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);

        $brf = new BuildResultFile();
        $brf->zip_submission_id = $submission->submission->id;
        $brf->gzipped = false;
        $brf->mimeType = 'application/xml';
        $brf->source = BuildResultFile::SOURCE_JUNIT;
        $brf->originalPath = '/dummy.xml';
        $brf->diskPath = Storage::putFile('submissions', 'test-resources/junit-xml-sample/sample/TEST-uk.ac.aston.autofeedback.junitxml.SampleTest.xml');
        $brf->save();

        $response = $this->actingAs($submission->author->user)->get(route('modules.items.show', [
            'module' => $submission->assessment->usage->teaching_module_id,
            'item' => $submission->assessment->usage->id
        ]));
        $response->assertSuccessful();
        $response->assertSee('java.lang.AssertionError: This should fail');
        $response->assertSee('java.lang.Exception: unexpected error');
        $response->assertSee('for stdout');
        $response->assertSee('for stderr');
    }

    public function testCannotShowSubmissionsWithoutPermission() {
        $this->setUpAssessment($item, $assessment, $tmu);
        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment ]));
        $response->assertForbidden();
    }

    public function testShowSubmissionsEmpty() {
        /**
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment ]));
        $response->assertSuccessful();
        $response->assertSee('No submissions');
    }

    public function testShowSubmissionsDefaultSort() {
        Storage::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        $submissions = AssessmentSubmission::factory(2)->create([
            'assessment_id' => $assessment->id,
            'teaching_module_user_id' => TeachingModuleUser::factory([
                'teaching_module_id' => $tmu->module->id,
            ])
        ]);

        // Default sort is by ascending ID
        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment ]));
        $response->assertSuccessful();
        $response->assertSeeInOrder([
            $submissions[0]->id, $submissions[1]->id
        ]);
    }

    public function testShowSubmissionsLatest() {
        Storage::fake();
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        /** @var AssessmentSubmission $submission */
        $submission = AssessmentSubmission::factory()->create([
            'assessment_id' => $assessment->id,
            'teaching_module_user_id' => TeachingModuleUser::factory([
                'teaching_module_id' => $tmu->module->id,
            ])
        ]);
        $submission->rerun();

        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment ]));
        $response->assertSuccessful();
        $this->assertEquals(2, $response->viewData('submissions')->submissions->count());

        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment, SubmissionsTable::SHOW_LATEST_KEY => true ]));
        $response->assertSuccessful();
        $this->assertEquals(1, $response->viewData('submissions')->submissions->count());
    }

    public function testShowSubmissionsPaginationLinkIncludesSortOptions()
    {
        Storage::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        /** @var AssessmentSubmission[] $submissions */
        AssessmentSubmission::factory(AssessmentController::ITEMS_PER_PAGE + 1)->create([
            'assessment_id' => $assessment->id,
            'teaching_module_user_id' => TeachingModuleUser::factory([
                'teaching_module_id' => $tmu->module->id,
            ])
        ]);

        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment ]));
        $response->assertSuccessful();
        $response->assertSee(route('modules.assessments.showSubmissions', [
            'module' => $tmu->module, 'assessment' => $assessment,
            SubmissionsTable::SORT_BY_QUERY_KEY => 'id',
            SubmissionsTable::SORT_ORDER_QUERY_KEY => 'asc',
            SubmissionsTable::SHOW_LATEST_KEY => false,
            'page' => 2
        ]));
    }

    public function testShowSubmissionsSubmitterSort() {
        Storage::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        /** @var AssessmentSubmission[] $submissions */
        $submissions = AssessmentSubmission::factory(2)->create([
            'assessment_id' => $assessment->id,
            'teaching_module_user_id' => TeachingModuleUser::factory([
                'teaching_module_id' => $tmu->module->id,
            ])
        ]);
        $submissions[0]->author->user->name = 'ZZZ';
        $submissions[0]->author->user->save();
        $submissions[1]->author->user->name = 'AAA';
        $submissions[1]->author->user->save();

        // Ascending sort
        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment, 'sortBy' => 'author', 'sortOrder' => 'asc' ]));
        $response->assertSuccessful();
        $response->assertSeeInOrder([
            $submissions[1]->author->user->name,
            $submissions[0]->author->user->name
        ]);

        // Descending sort
        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.showSubmissions',
            ['module' => $tmu->module, 'assessment' => $assessment, 'sortBy' => 'author', 'sortOrder' => 'desc' ]));
        $response->assertSuccessful();
        $response->assertSeeInOrder([
            $submissions[0]->author->user->name,
            $submissions[1]->author->user->name
        ]);
    }

    public function testCannotDownloadSubmissionsCSVWithoutPermission() {
        $this->setUpAssessment($item, $assessment, $tmu);
        $response = $this->actingAs($tmu->user)->get(route('modules.assessments.downloadSubmissionsCSV',
            ['module' => $tmu->module, 'assessment' => $assessment ]));
        $response->assertForbidden();
    }

    public function testDownloadSubmissionsCSV() {
        Queue::fake();
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        /** @var AssessmentSubmission[] $submissions */
        $submissions = AssessmentSubmission::factory(2)->create([
            'assessment_id' => $assessment->id,
            'teaching_module_user_id' => TeachingModuleUser::factory([
                'teaching_module_id' => $tmu->module->id,
            ])
        ]);
        $submissions[0]->points = AssessmentSubmission::POINTS_PENDING;
        $submissions[0]->save();
        $submissions[0]->rerun();

        $request = function($values) use ($tmu) {
            return $this->actingAs($tmu->user)->get(route('modules.assessments.downloadSubmissionsCSV', $values));
        };
        $response = $request(['module' => $tmu->module, 'assessment' => $assessment ]);

        $response->assertSuccessful();
        $response->assertHeader("Content-type", "text/csv; charset=UTF-8");
        $csvContent = $response->streamedContent();
        $this->assertEquals(4, substr_count($csvContent, "\n"), "The CSV should have header + one line per attempt");

        // Pending marks should show as empty cells
        $this->assertStringNotContainsString( AssessmentSubmission::POINTS_PENDING, $csvContent);

        // When asking to only download the rows for the latest attempts, the rerun of submissions[0] should not show up
        $response = $request([
            'module' => $tmu->module, 'assessment' => $assessment,
            SubmissionsTable::SHOW_LATEST_KEY => true
        ]);
        $response->assertHeader("Content-type", "text/csv; charset=UTF-8");
        $csvContent = $response->streamedContent();
        $this->assertEquals(3, substr_count($csvContent, "\n"),
            "The CSV should have header + one line per last attempt");
    }

    public function testRerunSubmissionsRequiresPermission() {
        Storage::fake();
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);

        $response = $this->rerunSubmissions($tmu, $assessment);
        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function testRerunSubmissionsNoFilters() {
        Storage::fake();
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION);

        $this->createSubmissions(2, $assessment, $tmu->module);
        $this->assertEquals(3, ZipSubmission::all()->count(),
            "Before rerunning, we should have the model solution ZIP and 2 submission ZIPs");
        $this->assertEquals(2, AssessmentSubmission::all()->count(),
            "Before rerunning, we should have 2 submissions");

        $response = $this->rerunSubmissions($tmu, $assessment, [
            'rerunInclude' => 'all',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Pushed twice and on the 'low' queue
        Queue::assertPushed(MavenBuildJob::class, 2);
        Queue::assertPushedOn(ZipSubmission::QUEUE_LOW, MavenBuildJob::class);

        // We should have new attempts
        $this->assertEquals(5, ZipSubmission::all()->count(),
            "After rerunning, we should have the model solution ZIP, 2 original ZIPs and 2 rerun ZIPs");
        $this->assertEquals(4, AssessmentSubmission::all()->count(),
            "After rerunning, we should have 2 original submissions and 2 reruns");
    }

    public function testRerunSubmissionSkipFullMarks() {
        Storage::fake();
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION);

        $maxMarks = '10.00';
        AssessmentTest::factory()->create([
            'points' => $maxMarks,
            'assessment_id' => $assessment->id,
        ]);

        /** @var AssessmentSubmission[] $submissions */
        $submissions = $this->createSubmissions(2, $assessment, $tmu->module);

        $submissions[0]->points = $maxMarks;
        $submissions[0]->save();
        $submissions[1]->points = '0.00';
        $submissions[1]->save();

        $this->assertEquals(0, $assessment->countOutdated());
        $this->assertEquals(1, $assessment->countFullMarks());
        $this->assertEquals($submissions[0]->id,
            $assessment->filteredSubmissions([Assessment::FILTER_FULL_MARKS => true])->first()->id);
        $this->assertEquals($submissions[1]->id,
            $assessment->filteredSubmissions([Assessment::FILTER_FULL_MARKS => false])->first()->id);

        $response = $this->rerunSubmissions($tmu, $assessment, [
            'rerunInclude' => 'all',
            'skipFullMarks' => 1,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(MavenBuildJob::class, 1);
        Queue::assertPushedOn(ZipSubmission::QUEUE_LOW, MavenBuildJob::class,
            function ($job) use ($submissions) {
                return $job->submission->id != $submissions[1]->submission->id;
            });
    }

    public function testRerunSubmissionOnlyOutdated() {
        Storage::fake();
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION);

        /** @var AssessmentSubmission[] $submissions */
        $submissions = $this->createSubmissions(2, $assessment, $tmu->module);

        /** @var ModelSolution $modelSolution */
        $modelSolution = ModelSolution::factory()->create([
            'assessment_id' => $assessment->id,
            'version' => $assessment->latestModelSolution->version + 1
        ]);
        $assessment->refresh();
        $submissions[0]->model_solution_id = $modelSolution->id;
        $submissions[0]->save();

        $this->assertEquals(1, $assessment->countOutdated());
        $this->assertEquals($submissions[1]->id,
            $assessment->filteredSubmissions([Assessment::FILTER_OUTDATED => true])->first()->id);
        $this->assertEquals($submissions[0]->id,
            $assessment->filteredSubmissions([Assessment::FILTER_OUTDATED => false])->first()->id);

        $response = $this->rerunSubmissions($tmu, $assessment, [
            'rerunInclude' => 'outdated',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(MavenBuildJob::class, 1);
        Queue::assertPushedOn(ZipSubmission::QUEUE_LOW, MavenBuildJob::class,
            function (MavenBuildJob $job) use ($submissions) {
                return $job->submission->id != $submissions[1]->submission->id;
            });
    }

    public function testRerunSubmissionOnlyMissingTests() {
        Storage::fake();
        Queue::fake();

        /**
         * @var Assessment $assessment
         * @var TeachingModuleUser $tmu
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION);

        /** @var AssessmentSubmission[] $submissions */
        $submissions = $this->createSubmissions(2, $assessment, $tmu->module);
        $submissions[0]->missing = 5;
        $submissions[0]->save();
        $submissions[1]->missing = 0;
        $submissions[1]->save();

        $this->assertEquals(1, $assessment->countMissing());
        $this->assertEquals($submissions[0]->id,
            $assessment->filteredSubmissions([Assessment::FILTER_MISSING => true])->first()->id);
        $this->assertEquals($submissions[1]->id,
            $assessment->filteredSubmissions([Assessment::FILTER_MISSING => false])->first()->id);

        $response = $this->rerunSubmissions($tmu, $assessment, [
            'rerunInclude' => 'missing',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(MavenBuildJob::class, 1);
        Queue::assertPushedOn(ZipSubmission::QUEUE_LOW, MavenBuildJob::class,
            function ($job) use ($submissions) {
                return $job->submission->id != $submissions[0]->submission->id;
            });
    }

    /**
     * @param $item
     * @param $assessment
     * @return string
     */
    private function getRouteForShow($item, $assessment): string
    {
        return route('modules.assessments.showModelSolution', [
            'module' => $item->teaching_module_id,
            'assessment' => $assessment->id,
        ]);
    }

    /**
     * @param TeachingModuleItem $item
     * @param Assessment $assessment
     * @return string
     */
    private function getRouteForStore(TeachingModuleItem $item, Assessment $assessment): string
    {
        return route('modules.assessments.storeModelSolution', [
            'module' => $item->teaching_module_id,
            'assessment' => $assessment->id,
        ]);
    }

    /**
     * @param $item
     * @param $assessment
     * @param $tmu
     * @param string[] $paths
     */
    private function setUpStoreOverrides(&$item, &$assessment, &$tmu, &$paths): void
    {
        Storage::fake('local');

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $paths = $assessment->latestModelSolution->submission->getFilePathsInZIP();
    }

    /**
     * @param $tmu
     * @param $assessment
     * @param string[] $selectedPaths
     * @return TestResponse
     */
    private function storeOverrides($tmu, $assessment, array $selectedPaths): TestResponse
    {
        return $this->actingAs($tmu->user)->post(
            route('modules.assessments.storeOverrides', [
                'module' => $tmu->teaching_module_id,
                'assessment' => $assessment->id,
            ]), [
            'paths' => $selectedPaths,
        ]);
    }

    /**
     * @param $assessment
     * @param $tests
     * @param $tmu
     */
    private function setUpAssessmentWithTests(&$assessment, &$tests, &$tmu): void
    {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create();
        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        $assessment->usage()->save($tmi);
        /** @var AssessmentTest[] $tests */
        $tests = AssessmentTest::factory()->count(5)->create([
            'assessment_id' => $assessment->id
        ]);
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $assessment->usage->teaching_module_id
        ]);
    }

    /**
     * @param TeachingModuleUser $tmu
     * @param Assessment $assessment
     * @return TestResponse
     */
    private function rerunSubmissions(TeachingModuleUser $tmu, Assessment $assessment, array $args = []): TestResponse
    {
        return $this->actingAs($tmu->user)->post(route('modules.assessments.rerunSubmissions', [
            'module' => $assessment->usage->module->id,
            'assessment' => $assessment->id,
        ]), $args);
    }

    /**
     * @param int $nSubmissions
     * @param Assessment $assessment
     * @param \App\TeachingModule $teachingModule
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|mixed
     */
    private function createSubmissions(int $nSubmissions, Assessment $assessment, \App\TeachingModule $teachingModule)
    {
        $submissions = AssessmentSubmission::factory($nSubmissions)->create([
            'assessment_id' => $assessment->id,
            'teaching_module_user_id' => TeachingModuleUser::factory([
                'teaching_module_id' => $teachingModule->id,
            ])
        ]);
        return $submissions;
    }

    /**
     * @return \Illuminate\Http\Testing\File
     */
    private function createSampleUploadedFile(): \Illuminate\Http\Testing\File
    {
// Zip up the submission
        $zipPath = 'test-resources/java-policy.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE, '', ['src/test/java', 'target']);
        $uploadedFile = UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath));
        return $uploadedFile;
    }

    /**
     * @param TeachingModuleItem $item
     * @param Assessment $assessment
     * @param TeachingModuleUser $tmu
     * @param $request
     */
    private function setUpMultipleAttemptTest(&$item, &$assessment, &$tmu, &$request): void
    {
        Storage::fake('local');
        Queue::fake();

        /**
         * @var TeachingModuleUser $tmu
         * @var Assessment $assessment
         * @var TeachingModuleItem $item
         */
        $this->setUpAssessment($item, $assessment, $tmu);
        $tmu->givePermissionTo(AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION);
        $tmu->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);

        // Zip up the submission
        $uploadedFile = $this->createSampleUploadedFile();

        // Generic request template
        $request = function () use ($tmu, $item, $assessment, $uploadedFile) {
            return $this->actingAs($tmu->user)->post(route('modules.assessments.storeSubmission', [
                'module' => $tmu->teaching_module_id,
                'assessment' => $assessment->id,
            ]), ['jobfile' => $uploadedFile, 'feedbackIntentUnderstood' => 1]);
        };
    }
}

