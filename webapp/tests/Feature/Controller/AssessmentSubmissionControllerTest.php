<?php
/**
 *  Copyright 2020 Aston University
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
use App\Jobs\MavenBuildJob;
use App\Policies\AssessmentSubmissionPolicy;
use App\Policies\TeachingModuleItemPolicy;
use App\Policies\ZipSubmissionPolicy;
use App\TeachingModuleUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Model\AssessmentTest;
use Tests\Feature\TMURoleSwitchingTestCase;

class AssessmentSubmissionControllerTest extends TMURoleSwitchingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    public function testDeleteSubmission()
    {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $asub->assessment->usage->teaching_module_id,
        ]);

        $request = function () use ($tmu, $asub) {
            return $this->actingAs($tmu->user)->delete(route('modules.submissions.destroy', [
                'module' => $tmu->teaching_module_id,
                'submission' => $asub->id,
            ]));
        };

        $response = $request();
        $response->assertForbidden();

        $zip = $asub->submission;
        $tmu->user->givePermissionTo(AssessmentSubmissionPolicy::DELETE_SUBMISSION_PERMISSION);
        $response = $request();
        $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDeleted($asub);
        $this->assertDeleted($zip);
        TeachingModuleUser::findOrFail($asub->teaching_module_user_id);
        Assessment::findOrFail($asub->assessment_id);
    }

    public function testShowOwnPermission() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        $asub->author->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);

        // First, try to view the submission while the item is available: it should work
        $response = $this->showSubmission($asub);
        $response->assertSuccessful();
        $response->assertViewHas(['submission' => $asub]);

        // Now flag it as unavailable and try again: it should not be visible to the user now
        $asub->assessment->usage->available = false;
        $asub->assessment->usage->save();
        $response = $this->showSubmission($asub);
        $response->assertForbidden();
    }

    public function testShowWithOneTask() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        $asub->author->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);

        /** @var \App\AssessmentTest $atest */
        $atest = \App\AssessmentTest::factory()->create([
            'assessment_id' => $asub->assessment_id,
        ]);
        $atest->task = 'Sample task';
        $atest->save();

        $response = $this->showSubmission($asub);
        $response->assertSuccessful();
        $response->assertSee($atest->task);
        $response->assertSee($atest->class_name);
        $response->assertSee($atest->name);
    }

    public function testShowWithOneTaskOneTestWithoutTask() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        $asub->author->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);

        /** @var \App\AssessmentTest[] $atests */
        $atests = \App\AssessmentTest::factory()->count(2)->create([
            'assessment_id' => $asub->assessment_id,
        ]);
        $atests[0]->task = 'Sample task';
        $atests[0]->save();

        $response = $this->showSubmission($asub);
        $response->assertSuccessful();
        $response->assertSee($atests[0]->task);
        $response->assertSee($atests[0]->class_name);
        $response->assertSee($atests[0]->name);

        $response->assertSee(\App\AssessmentSubmission::TASK_NOT_ASSIGNED);
        $response->assertSee($atests[1]->class_name);
        $response->assertSee($atests[1]->name);
    }

    public function testShowNotOwnedSubmissionRequiresPermission() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $asub->assessment->usage->module->id
        ]);

        $response = $this->actingAs($tmu->user)->get(route('modules.submissions.show', [
            'module' => $asub->assessment->usage->module,
            'submission' => $asub->id
        ]));
        $response->assertForbidden();
    }

    public function testShowNotOwnedSubmissionWorksWithPermission() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $asub->assessment->usage->module->id
        ]);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        $response = $this->actingAs($tmu->user)->get(route('modules.submissions.show', [
            'module' => $asub->assessment->usage->module,
            'submission' => $asub->id
        ]));
        $response->assertSuccessful();
        $response->assertViewHas(['submission' => $asub]);
        $response->assertSee($asub->author->user->name);
        $response->assertSee($asub->author->user->email);
    }

   public function testDeleteSubmissionRedirectsToSubmissionsTable() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $asub->assessment->usage->module->id
        ]);
        $tmu->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        $tmu->givePermissionTo(AssessmentSubmissionPolicy::DELETE_SUBMISSION_PERMISSION);

        $routeParams = [
            'module' => $asub->assessment->usage->module,
            'submission' => $asub->id
        ];

        $response = $this->actingAs($tmu->user)
            ->from(route('modules.submissions.show', $routeParams))
            ->followingRedirects()
            ->delete(route('modules.submissions.destroy', $routeParams));
        $response->assertSuccessful();
        $this->assertDeleted($asub);
    }

    public function testRerunBySubmitterRequiresModelSolutionToBeOlder() {
        Queue::fake();

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        $fnInvocation = function () use ($asub) {
            return $this->actingAs($asub->author->user)->post(route('modules.submissions.rerun', [
                'module' => $asub->assessment->usage->module,
                'submission' => $asub->id
            ]));
        };
        $response = $fnInvocation();
        $response->assertForbidden();

        /*
         * Need to wait 2 seconds to ensure that the updated_at timestamp changes upon saving (Carbon::setTestNow
         * does not help).
         */
        sleep(2);
        $asub->assessment->latestModelSolution->submission->sha256 = 'dummy';
        $asub->assessment->latestModelSolution->submission->save();

        $response = $fnInvocation();
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(MavenBuildJob::class);
    }

    public function testRerunByOtherRequiresPermission() {
        Queue::fake();

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $asub->assessment->usage->module->id
        ]);

        $fnInvocation = function () use ($asub, $tmu) {
            return $this->actingAs($tmu->user)->post(route('modules.submissions.rerun', [
                'module' => $asub->assessment->usage->module,
                'submission' => $asub->id
            ]));
        };
        $response = $fnInvocation();
        $response->assertForbidden();
        Queue::assertNothingPushed();
        $tmu->givePermissionTo(AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION);
        Auth::user()->moduleUser($tmu->module)->refresh();

        $response = $fnInvocation();
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(MavenBuildJob::class);
    }

    /**
     * @param AssessmentSubmission $asub
     * @return \Illuminate\Testing\TestResponse
     */
    private function showSubmission(AssessmentSubmission $asub): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($asub->author->user)->get(route('modules.submissions.show', [
            'module' => $asub->assessment->usage->module,
            'submission' => $asub->id
        ]));
    }

}

