<?php

/**
 *  Copyright 2021 Aston University
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
use App\Http\Controllers\AssessmentSubmissionComparisonController as Controller;
use App\Policies\AssessmentPolicy;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Feature\TMURoleSwitchingTestCase;

class AssessmentSubmissionComparisonControllerTest extends TMURoleSwitchingTestCase
{
    /**
     * @var TeachingModuleItem
     */
    private $item;

    /**
     * @var Assessment
     */
    private $assessment;

    /**
     * @var TeachingModuleUser
     */
    private $tmu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = TeachingModuleItem::factory()->create();
        $this->assessment = Assessment::factory()->create();
        $this->assessment->usage()->save($this->item);
        $this->tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' =>  $this->item->teaching_module_id
        ]);
    }

    public function testCSVFormFailsWithoutPermission() {
        $response = $this->actingAs($this->tmu->user)->showCSVForm();
        $response->assertForbidden();
    }

    public function testCSVFormSucceedsWithPermission() {
        $this->grantComparePermission();
        $response = $this->actingAs($this->tmu->user)->showCSVForm();
        $response->assertSuccessful();
    }

    public function testCompareFailsWithoutPermission() {
        $response = $this->actingAs($this->tmu->user)->processCSV();
        $response->assertForbidden();
    }

    public function testCompareRedirectsWithoutCSVFile() {
        $this->grantComparePermission();
        $response = $this->actingAs($this->tmu->user)->processCSV();
        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function testCompareAllSame() {
        $this->grantComparePermission();
        $asub = $this->createAssessment();

        $response = $this->actingAs($this->tmu->user)->processCSV([
            'csvfile' => $this->createSingleRowCSV(
                $asub->submission->filename, $asub->submission->sha256, $this->tmu->user->email)
        ]);

        $response->assertSuccessful();
        $response->assertViewHas(Controller::VP_DIFFERENT_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_MODULE, []);
    }

    public function testCompareDifferentSHA256() {
        $this->grantComparePermission();
        $asub = $this->createAssessment();

        $otherFilename = 'other-file.zip';
        $otherSHA256 = '_different_';
        $response = $this->actingAs($this->tmu->user)->processCSV([
            'csvfile' => $this->createSingleRowCSV(
                $otherFilename, $otherSHA256, $this->tmu->user->email)
        ]);

        $response->assertSuccessful();
        $response->assertViewHas(Controller::VP_NOT_IN_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_MODULE, []);

        $differentExternal = $response->viewData(Controller::VP_DIFFERENT_EXTERNAL);
        $this->assertCount(1, $differentExternal);

        $diff = $differentExternal[0];
        $this->assertEquals($otherFilename, $diff[Assessment::COMPARE_FILENAME]);
        $this->assertEquals($otherSHA256, $diff[Assessment::COMPARE_SHA256_EXTERNAL]);
        $this->assertEquals($this->tmu->id, $diff[Assessment::COMPARE_USER]->id);
        $this->assertEquals($asub->id, $diff[Assessment::COMPARE_ASSESSMENT_SUBMISSION]->id);

        $response->assertSee($this->showSubmissionURL($asub));
    }

    public function testCompareOnlyInExternal() {
        $this->grantComparePermission();

        $otherFilename = 'other-file.zip';
        $otherSHA256 = '_different_';
        $response = $this->actingAs($this->tmu->user)->processCSV([
            'csvfile' => $this->createSingleRowCSV(
                $otherFilename, $otherSHA256, $this->tmu->user->email)
        ]);

        $response->assertSuccessful();
        $response->assertViewHas(Controller::VP_NOT_IN_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_MODULE, []);

        $differentExternal = $response->viewData(Controller::VP_DIFFERENT_EXTERNAL);
        $this->assertCount(1, $differentExternal);

        $diff = $differentExternal[0];
        $this->assertEquals($otherFilename, $diff[Assessment::COMPARE_FILENAME]);
        $this->assertEquals($otherSHA256, $diff[Assessment::COMPARE_SHA256_EXTERNAL]);
        $this->assertEquals($this->tmu->id, $diff[Assessment::COMPARE_USER]->id);
        $this->assertArrayNotHasKey(Assessment::COMPARE_ASSESSMENT_SUBMISSION, $diff);
    }

    public function testCompareNotInExternal() {
        $this->grantComparePermission();
        $asub = $this->createAssessment();

        $response = $this->actingAs($this->tmu->user)->processCSV([
            'csvfile' => $this->createCSV()
        ]);

        $response->assertSuccessful();
        $response->assertViewHas(Controller::VP_DIFFERENT_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_MODULE, []);

        $notInExternal = $response->viewData(Controller::VP_NOT_IN_EXTERNAL);
        $this->assertCount(1, $notInExternal);

        $diff = $notInExternal[0];
        $this->assertEquals($this->tmu->id, $diff[Assessment::COMPARE_USER]->id);
        $this->assertEquals($asub->id, $diff[Assessment::COMPARE_ASSESSMENT_SUBMISSION]->id);

        $response->assertSee($this->showSubmissionURL($asub));
    }

    public function testCompareNotEnrolled() {
        $this->grantComparePermission();

        $otherEmail = 'unknown.email@example.com';
        $response = $this->actingAs($this->tmu->user)->processCSV([
            'csvfile' => $this->createSingleRowCSV(
                'other-file.zip', '_different_', $otherEmail)
        ]);

        $response->assertSuccessful();
        $response->assertViewHas(Controller::VP_DIFFERENT_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_EXTERNAL, []);
        $response->assertViewHas(Controller::VP_NOT_IN_MODULE, [$otherEmail]);
    }

    private function showCSVForm(): TestResponse {
        return $this->get(route('modules.assessments.compare.showCSVForm', [
            'module' => $this->item->module,
            'assessment' => $this->assessment
        ]));
    }

    private function processCSV($args = []) {
        return $this->post(route('modules.assessments.compare.processCSV', [
            'module' => $this->item->module,
            'assessment' => $this->assessment
        ]), $args);
    }

    private function createSingleRowCSV($filename, $sha256, $email) {
        return $this->createCSV([
            [
                Controller::EMAIL_COLUMN => $email,
                Controller::SHA256_COLUMN => $sha256,
                Controller::FILENAME_COLUMN => $filename,
            ],
        ]);
    }

    private function createCSV($rows = []) {
        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, [
            Controller::FILENAME_COLUMN,
            Controller::SHA256_COLUMN,
            Controller::EMAIL_COLUMN
        ]);
        foreach ($rows as $row) {
            fputcsv($buffer, [
                $row[Controller::FILENAME_COLUMN],
                $row[Controller::SHA256_COLUMN],
                $row[Controller::EMAIL_COLUMN]
            ]);
        }
        rewind($buffer);
        $csv = fread($buffer, 10240);
        fclose($buffer);

        return UploadedFile::fake()->createWithContent('file.csv', $csv);
    }

    private function grantComparePermission(): void {
        $this->tmu->givePermissionTo(AssessmentPolicy::COMPARE_PERMISSION);
    }

    /**
     * @return AssessmentSubmission
     */
    private function createAssessment(): AssessmentSubmission
    {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create([
            'teaching_module_user_id' => $this->tmu->id,
            'assessment_id' => $this->assessment->id,
        ]);
        return $asub;
    }

    /**
     * @param AssessmentSubmission $asub
     * @return string
     */
    private function showSubmissionURL(AssessmentSubmission $asub): string
    {
        return route('modules.submissions.show', [
            'module' => $this->tmu->module,
            'submission' => $asub,
        ]);
    }
}
