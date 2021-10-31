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

use App\AssessmentSubmission;
use App\BuildResultFile;
use App\Policies\ZipSubmissionPolicy;
use App\TeachingModuleUser;
use App\User;
use App\Zip\ExtendedZipArchive;
use App\ZipSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class JobControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function testIndexJobsNormalForbidden()
    {
        $user = \App\User::factory()->create();
        $response = $this->actingAs($user)->get(route('jobs.index'));
        $response->assertForbidden();
    }

    public function testIndexJobsAdminEmpty() {
        /** @var User $user */
        $user = \App\User::factory()->create();
        $user->assignRole(User::SUPER_ADMIN_ROLE);
        User::setAdminMode(true);

        $response = $this->actingAs($user)->get(route('jobs.index'));
        $response->assertSuccessful();
        $response->assertViewHas('jobs');
    }

    public function testIndexJobsPermissionsEmpty() {
        /** @var User $user */
        $user = \App\User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        $response = $this->actingAs($user)->get(route('jobs.index'));
        $response->assertSuccessful();
        $response->assertViewHas('jobs');
    }

    public function testCreateJobsNormalForbidden() {
        $user = \App\User::factory()->create();
        $response = $this->actingAs($user)->get(route('jobs.create'));
        $response->assertForbidden();
    }

    public function testCreateJobsPermissionAllowed() {
        $user = \App\User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::CREATE_RAW_PERMISSION);
        $response = $this->actingAs($user)->get(route('jobs.create'));
        $response->assertSuccessful();
    }

    public function testStoreJobForbidden() {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('jobs.store'), [
            'jobfile' => UploadedFile::fake()
        ]);
        $response->assertForbidden();
    }

    public function testStoreJob() {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::CREATE_RAW_PERMISSION);

        $zipPath = 'test-resources/java-policy.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $response = $this->actingAs($user)->post(route('jobs.store'), [
            'jobfile' => UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath))
        ]);
        $response->assertRedirect(route('jobs.show', ZipSubmission::first()));

        /** @var ZipSubmission $zs */
        $zs = ZipSubmission::first();
        Storage::disk('local')->assertExists($zs->diskPath);
        $this->assertEquals($user->id, $zs->user_id);
        $this->assertEquals($user->id, $zs->user->id);
        $this->assertEquals($user->id, $zs->submitter_user_id);
        $this->assertEquals($user->id, $zs->submitter->id);
        $this->assertEquals(ZipSubmission::STATUS_OK, $zs->status);
        $this->assertNotEquals(ZipSubmission::SHA256_PENDING, $zs->sha256);
        $this->assertTrue($zs->resultFiles()->where('source', BuildResultFile::SOURCE_JUNIT)->exists());
    }

    public function testShowJobForbidden() {
        $job = $this->createFakeJob();
        $user = User::factory()->create();
        $response = $this->showJob($user, $job);
        $response->assertForbidden();
    }

    public function testShowJobAuthorized() {
        $job = $this->createFakeJob();

        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        $response = $this->showJob($user, $job);
        $response->assertSuccessful();
        $response->assertViewHas('job', $job);
        $response->assertViewHas('results');
        $response->assertSee($job->user->name);
        $response->assertSee($job->user->email);
        $response->assertDontSee('Submitter');

        // If ?backPage= is not specified, we shouldn't see a Back button
        $response->assertDontSee("Back");

        $response = $this->actingAs($user)->get(route('jobs.show', ['job' => $job, 'backPage' => 1]));
        $response->assertSuccessful();
        $response->assertSee("Back");
    }

    public function testShowJobDifferentAuthorSubmitter() {
        $job = $this->createFakeJob();
        $job->submitter_user_id = User::factory()->create()->id;
        $job->save();

        $response = $this->showJob($job->user, $job);
        $response->assertSuccessful();
        $response->assertSee('Author');
        $response->assertSee($job->user->name);
        $response->assertSee($job->user->email);
        $response->assertSee('Submitter');
        $response->assertSee($job->submitter->name);
        $response->assertSee($job->submitter->email);
    }

    public function testShowOwnSubmissionAuthorized() {
        /** @var AssessmentSubmission $submission */
        $submission = AssessmentSubmission::factory()->create();
        $response = $this->actingAs($submission->author->user)->get(
            route('jobs.show', $submission->submission->id)
        );
        $response->assertSuccessful();
    }

    public function testShowSubmissionFromEnrolledModule() {
        /** @var AssessmentSubmission $submission */
        $submission = AssessmentSubmission::factory()->create();

        /** @var TeachingModuleUser $tutorUser */
        $tutorUser = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $submission->assessment->usage->teaching_module_id,
        ]);

        // Before the user gets the permission, they cannot access the submission.
        $showJobURL = route('jobs.show', $submission->submission->id);
        $response = $this->actingAs($tutorUser->user)->get($showJobURL);
        $response->assertForbidden();

        // After the user gets the permission, they can access the submission
        $tutorUser->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        Auth::user()->moduleUser($tutorUser->module)->refresh();
        $response = $this->actingAs($tutorUser->user)->get($showJobURL);
        $response->assertSuccessful();

        // That permission doesn't work for submissions in other modules
        /** @var AssessmentSubmission $submissionOtherModule */
        $submissionOtherModule = AssessmentSubmission::factory()->create();
        $response = $this->actingAs($tutorUser->user)->get(
            route('jobs.show', $submissionOtherModule->submission->id));
        $response->assertForbidden();
    }

    public function testShowResultForbidden() {
        Storage::fake('local');

        /** @var BuildResultFile $brf */
        $brf = BuildResultFile::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($brf->url($brf->submission));
        $response->assertForbidden();
    }

    public function testShowResultAuthorizedGZipped() {
        Storage::fake('local');

        /** @var BuildResultFile $brf */
        $brf = BuildResultFile::factory()->create([
            'mimeType' => 'text/css',
        ]);
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        $response = $this->actingAs($user)->get($brf->url($brf->submission));
        $response->assertSuccessful();
        $response->assertHeader('Content-Encoding', 'gzip');
        $response->assertHeader('Content-Type', $brf->mimeType . '; charset=UTF-8');
    }

    public function testShowResultAuthorizedNotGZipped() {
        Storage::fake('local');

        /** @var BuildResultFile $brf */
        $brf = BuildResultFile::factory()->create([
            'mimeType' => 'image/gif',
        ]);
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        $response = $this->actingAs($user)->get($brf->url($brf->submission));
        $response->assertSuccessful();
        $response->assertHeaderMissing('Content-Encoding');
        $response->assertHeader('Content-Type', $brf->mimeType);
    }

    public function testDestroyForbidden() {
        Storage::fake('local');

        /** @var BuildResultFile $brf */
        $brf = BuildResultFile::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('jobs.destroy',  $brf->submission->id));
        $response->assertForbidden();
        BuildResultFile::findOrFail($brf->id);
    }

    public function testDestroyAuthorized() {
        Storage::fake('local');

        /** @var BuildResultFile $brf */
        $brf = BuildResultFile::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::DELETE_ANY_PERMISSION);

        $this->assertTrue(Storage::exists($brf->submission->diskPath));
        $this->assertTrue(Storage::exists($brf->diskPath));

        $response = $this->actingAs($user)->delete(route('jobs.destroy',  $brf->submission->id));
        $response->assertRedirect(route('jobs.index'));
        $response->assertSessionHas('status');

        $this->assertDeleted($brf->submission);
        $this->assertDeleted($brf);

        $this->assertFalse(Storage::exists($brf->submission->diskPath));
        $this->assertFalse(Storage::exists($brf->diskPath));
    }

    public function testDestroyManyUnauthorized() {
        Storage::fake('local');

        /** @var BuildResultFile $job */
        $job = ZipSubmission::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('jobs.destroyMany'), [
            'jobIDs' => [$job->id]
        ]);
        $response->assertForbidden();
    }

    public function testDestroyManyAdmin() {
        Storage::fake('local');

        /** @var BuildResultFile $jobs */
        $jobs = ZipSubmission::factory()->count(3)->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::DELETE_ANY_PERMISSION);

        $response = $this->actingAs($user)->delete(route('jobs.destroyMany'), [
            'jobIDs' => [$jobs[0]->id, $jobs[1]->id]
        ]);
        $response->assertRedirect(route('jobs.index'));
        $response->assertSessionHas('status');

        $this->assertDeleted($jobs[0]);
        $this->assertDeleted($jobs[1]);
        ZipSubmission::findOrFail($jobs[2]->id);
    }

    public function testDestroyManyNotFound() {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::DELETE_ANY_PERMISSION);

        $response = $this->actingAs($user)->delete(route('jobs.destroyMany'), [
            'jobIDs' => [42]
        ]);
        $response->assertRedirect();
    }

    private function createFakeJob(): ZipSubmission {
        Storage::fake('local');

        /** @var ZipSubmission $job */
        $job = ZipSubmission::factory()->create();
        $job->resultFiles()->createMany(
            BuildResultFile::factory()->count(10)->make([
                'zip_submission_id' => $job->id,
            ])->toArray()
        );

        return $job;
    }

    public function testDownloadForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var ZipSubmission $job */
        $job = ZipSubmission::factory()->create();

        $response = $this->actingAs($user)->get(route('jobs.download', $job->id));
        $response->assertForbidden();
    }

    public function testDownloadAdmin() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        /** @var ZipSubmission $job */
        $job = ZipSubmission::factory()->create();

        $response = $this->actingAs($user)->get(route('jobs.download', $job->id));
        $response->assertSuccessful();
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="' . $job->filename . '"');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $user
     * @param ZipSubmission $job
     * @return \Illuminate\Testing\TestResponse
     */
    private function showJob(\Illuminate\Database\Eloquent\Model $user, ZipSubmission $job): \Illuminate\Testing\TestResponse
    {
        $response = $this->actingAs($user)->get(route('jobs.show', $job->id));
        return $response;
    }

}
