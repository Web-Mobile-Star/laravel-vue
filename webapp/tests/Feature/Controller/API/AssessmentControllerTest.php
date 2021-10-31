<?php

/**
 *  Copyright 2020-2021 Aston University
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

namespace Tests\Feature\Controller\API;

use App\Assessment;
use App\AssessmentSubmission;
use App\Jobs\MavenBuildJob;
use App\Policies\AssessmentPolicy;
use App\Policies\TeachingModuleItemPolicy;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\Zip\ExtendedZipArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class AssessmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function testSubmission() {
        $this->setupAssessment($assessment, $tmi);

        /** @var TeachingModuleUser $tmu */
        $this->setupFakeUser($tmi, $tmu, [
            'teaching_module_id' => $tmi->teaching_module_id
        ]);
        $this->setupSubmissionFile($uploadedFile);

        // No token - should be redirected to login page
        $args = ['jobfile' => $uploadedFile];
        $response = $this->storeSubmission($assessment, $args);
        $response->assertRedirect(route('login'));

        // Set up token and try again
        Sanctum::actingAs($tmu->user, ['*']);
        $response = $this->storeSubmission($assessment, $args);
        $response->assertSuccessful();
        $this->assertNotNull(AssessmentSubmission::first());
        $this->assertSuccessfulSubmissionResponse($response, route(
            'modules.items.show', ['module' => $tmi->module->id, 'item' => $tmi->id]
        ));
    }

    public function testSubmissionOnBehalfOf() {
        Queue::fake();
        $this->setupAssessment($assessment, $tmi);

        $options = ['teaching_module_id' => $tmi->teaching_module_id];
        /** @var TeachingModuleUser $tmuSubmitter */
        $this->setupFakeUser($tmi, $tmuSubmitter, $options);
        /** @var TeachingModuleUser $tmuAuthor */
        $tmuAuthor = TeachingModuleUser::factory()->create($options);

        $this->setupSubmissionFile($uploadedFile);

        // 1. Without the special permission, it shouldn't go through
        Sanctum::actingAs($tmuSubmitter->user, ['*']);
        $args = ['jobfile' => $uploadedFile, 'authorEmail' => $tmuAuthor->user->email];
        $response = $this->storeSubmission($assessment, $args);
        $response->assertForbidden();
        Queue::assertNothingPushed();

        // 2. With permission but an incorrect email, it still shouldn't go through
        $tmuSubmitter->user->givePermissionTo(AssessmentPolicy::UPLOAD_SUBMISSION_ON_BEHALF_OF_PERMISSION);
        Auth::user()->moduleUser($tmuSubmitter->module)->refresh();
        $response = $this->storeSubmission($assessment, [
            'jobfile' => $uploadedFile, 'authorEmail' => 'missing@example.com'
        ]);
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message' => [],
            'errors' => ['authorEmail',]
        ]);
        Queue::assertNothingPushed();

        // 3. With the right permission and email, it should go through
        $response = $this->storeSubmission($assessment, $args);
        $response->assertSuccessful();
        Queue::assertPushed(MavenBuildJob::class, 1);

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::first();
        $this->assertNotNull($asub);
        $this->assertSuccessfulSubmissionResponse($response, route(
            'modules.submissions.show', ['module' => $tmi->module->id, 'submission' => $asub->id]
        ));
        $this->assertEquals($tmuAuthor->id, $asub->teaching_module_user_id);
        $this->assertEquals(1, $asub->attempt);
        $this->assertEquals($tmuSubmitter->user->id, $asub->submission->submitter_user_id);
        $this->assertEquals($tmuAuthor->user->id, $asub->submission->user_id);
    }

    /**
     * @param $assessment
     * @param $tmi
     */
    private function setupAssessment(&$assessment, &$tmi): void
    {
        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();

        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create();
        $assessment->usage()->save($tmi);
    }

    /**
     * @param $uploadedFile
     */
    private function setupSubmissionFile(&$uploadedFile): void
    {
        // Zip up the submission (same as solution but without the test file, to test file overrides)
        $zipPath = 'test-resources/java-policy.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE, '', ['src/test/java', 'target']);
        $uploadedFile = UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath));
    }

    /**
     * @param $tmi
     * @param $tmu
     * @param $options
     */
    private function setupFakeUser($tmi, &$tmu, $options): void
    {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create($options);

        $tmu->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);
        $tmu->givePermissionTo(AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION);
    }

    /**
     * @param TestResponse $response
     * @param $expectedURL
     */
    private function assertSuccessfulSubmissionResponse(TestResponse $response, $expectedURL): void
    {
        $response->assertJsonStructure(['message', 'url']);
        $response->assertJson(['url' => $expectedURL]);
    }

    /**
     * @param $assessment
     * @param $uploadedFile
     * @return TestResponse
     */
    private function storeSubmission($assessment, $args): TestResponse
    {
        return $this->post(route('api.assessments.storeSubmission', ['assessment' => $assessment->id]), $args);
    }

}
