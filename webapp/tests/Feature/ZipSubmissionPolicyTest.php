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

namespace Tests\Feature;

use App\Assessment;
use App\AssessmentSubmission;
use App\ModelSolution;
use App\Policies\AssessmentPolicy;
use App\Policies\AssessmentSubmissionPolicy;
use App\Policies\ZipSubmissionPolicy;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\ZipSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ZipSubmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ZipSubmissionPolicy
     */
    private $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->policy = new ZipSubmissionPolicy();
    }

    public function testViewModelSubmissionPermission() {
        /** @var ZipSubmission $submission */
        $submission = ZipSubmission::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(AssessmentPolicy::VIEW_MODEL_SOLUTION_PERMISSION);

        // Normally, the user shouldn't be able to see it
        $this->assertNull($this->policy->view($tmu->user, $submission));

        // But if we make it into a model solution, it should be able to see it
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id,
        ]);

        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        $assessment->usage()->save($tmi);
        ModelSolution::factory()->create([
            'assessment_id' => $assessment->id,
            'zip_submission_id'  => $submission->id,
            'version' => $assessment->latestModelSolution->version + 1,
        ]);
        $submission->refresh();

        $this->assertTrue($this->policy->view($tmu->user, $submission));
    }

    public function testViewOwnSubmissionPermission() {
        /** @var ZipSubmission $submission */
        $submission = ZipSubmission::factory()->create();

        // A user can see their own submissions
        $this->assertTrue($this->policy->view($submission->user, $submission));
    }

    public function testRawJobDelete() {
        /** @var ZipSubmission $zsub */
        $zsub = ZipSubmission::factory()->create();

        // Even the author needs explicit permission in order to be able to delete
        $this->assertNull($this->policy->delete($zsub->user, $zsub));
        $zsub->user->givePermissionTo(ZipSubmissionPolicy::DELETE_ANY_PERMISSION);
        $this->assertTrue($this->policy->delete($zsub->user, $zsub));
    }

    public function testCannotDeleteAssessmentSubmission() {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        $asub->submission->user->givePermissionTo(ZipSubmissionPolicy::DELETE_ANY_PERMISSION);

        // Even with the permission, if a job is a submission for an assessment, it cannot be deleted
        $this->assertFalse($this->policy->delete($asub->submission->user, $asub->submission));
    }

    public function testCannotDeleteModelSolution() {
        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        $assessment->latestModelSolution->submission->user->givePermissionTo(ZipSubmissionPolicy::DELETE_ANY_PERMISSION);

        // Even with the permission, if a job is a model solution for an assessment, it cannot be deleted
        $this->assertFalse($this->policy->delete(
            $assessment->latestModelSolution->submission->user,
            $assessment->latestModelSolution->submission));
    }

}
