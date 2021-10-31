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

namespace Tests\Feature\Jobs;

use App\AssessmentSubmission;
use App\AssessmentTest;
use App\BuildResultFile;
use App\Jobs\MarkSubmissionJob;
use App\ZipSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarkSubmissionJobTest extends TestCase
{
    use RefreshDatabase;

    const SAMPLE_JUNIT_XML = 'test-resources/junit-xml-sample/sample/TEST-uk.ac.aston.autofeedback.junitxml.SampleTest.xml';
    const JUNIT_CLASS_NAME = 'uk.ac.aston.autofeedback.junitxml.SampleTest';

    public function testMarkingNoInfo()
    {
        /** @var AssessmentSubmission $asub */
        $this->setupSubmission($asub);
        MarkSubmissionJob::dispatch($asub->id);

        $asub->refresh();
        $this->assertEquals('0.00', $asub->points);
        $this->assertEquals(3, $asub->passed);
        $this->assertEquals(1, $asub->failed);
        $this->assertEquals(1, $asub->errors);
        $this->assertEquals(1, $asub->skipped);
        $this->assertEquals(0, $asub->missing);
    }

    public function testMarkingPending() {
        $this->assertMarkingWithIncompleteSubmissionMakesNoChanges(ZipSubmission::STATUS_PENDING);
    }

    public function testMarkingAborted() {
        $this->assertMarkingWithIncompleteSubmissionMakesNoChanges(ZipSubmission::STATUS_ABORTED);
    }

    public function testMarkingOneHasPoints() {
        /** @var AssessmentSubmission $asub */
        $this->setupSubmission($asub);
        $this->createTest('passingTest', '2.55', $asub);

        MarkSubmissionJob::dispatch($asub->id);
        $asub->refresh();
        $this->assertEquals('2.55', $asub->points);
    }

    public function testMarkingTwoHasPointsOneFailed() {
        /** @var AssessmentSubmission $asub */
        $this->setupSubmission($asub);
        $this->createTest('passingTest', '2.05', $asub);
        $this->createTest('testWithStdout', '1.105', $asub);
        $this->createTest('failingTest', '1.50', $asub);

        MarkSubmissionJob::dispatch($asub->id);
        $asub->refresh();

        // Note: bcmath rounds up (2.05 + 1.105 = 3.155 ~ 3.16)
        $this->assertEquals('3.16', $asub->points);
    }

    private function assertMarkingWithIncompleteSubmissionMakesNoChanges(int $status) {
        /** @var AssessmentSubmission $asub */
        $this->setupSubmission($asub);
        $asub->submission->status = $status;
        $asub->submission->save();
        MarkSubmissionJob::dispatch($asub->id);

        $asub->refresh();
        $this->assertEquals(AssessmentSubmission::POINTS_PENDING, $asub->points);
        $this->assertEquals(0, $asub->passed);
        $this->assertEquals(0, $asub->failed);
        $this->assertEquals(0, $asub->errors);
        $this->assertEquals(0, $asub->skipped);
        $this->assertEquals(0, $asub->missing);
    }

    /**
     * @param $asub
     */
    private function setupSubmission(&$asub): void
    {
        Storage::fake('local');

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create([
            'points' =>  AssessmentSubmission::POINTS_PENDING,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'missing' => 0,
        ]);
        $this->assertNotNull($asub->assessment->usage);

        $zsub = $asub->submission;
        $junitResult = BuildResultFile::createFrom($zsub, 'junit',
            self::SAMPLE_JUNIT_XML, dirname(self::SAMPLE_JUNIT_XML));
        $zsub->resultFiles()->save($junitResult);
    }

    /**
     * @param string $testName
     * @param string $testPoints
     * @param AssessmentSubmission $asub
     */
    private function createTest(string $testName, string $testPoints, AssessmentSubmission $asub): void
    {
        $aTest = new AssessmentTest;
        $aTest->class_name = self::JUNIT_CLASS_NAME;
        $aTest->name = $testName;
        $aTest->points = $testPoints;
        $aTest->feedback_markdown = '';
        $aTest->assessment_id = $asub->assessment_id;
        $aTest->save();
    }
}
