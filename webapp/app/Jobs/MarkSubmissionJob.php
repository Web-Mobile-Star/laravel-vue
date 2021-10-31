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

namespace App\Jobs;

use App\AssessmentSubmission;
use App\BuildResultFile;
use App\Events\SubmissionMarksUpdated;
use App\JUnit\JUnitXMLParser;
use App\ZipSubmission;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MarkSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int ID of the AssessmentSubmission that should be marked if it still exists by the time we run this job.
     */
    public $aSubmissionId;

    /**
     * Create a new job instance.
     *
     * @param int $asubId
     */
    public function __construct(int $asubId)
    {
        $this->onQueue(ZipSubmission::QUEUE_NON_JAVA);
        $this->aSubmissionId = $asubId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /** @var AssessmentSubmission|null $aSubmission */
        $aSubmission = AssessmentSubmission::find($this->aSubmissionId);
        if (!$aSubmission) {
            Log::info("Submission " . $this->aSubmissionId . " no longer exists, aborting marking");
            return;
        }

        $zipSubmission = $aSubmission->submission;
        if ($zipSubmission->status == ZipSubmission::STATUS_PENDING || $zipSubmission->status == ZipSubmission::STATUS_ABORTED) {
            Log::info("Submission "
                . $this->aSubmissionId . " has not completed its execution (status is "
                . $zipSubmission->status . "), skipping marking");
            return;
        }

        Log::info("Marking submission " . $this->aSubmissionId);

        $aSubmission->points = '0.00';
        $aSubmission->passed = 0;
        $aSubmission->skipped = 0;
        $aSubmission->failed = 0;
        $aSubmission->errors = 0;
        $aSubmission->missing = 0;

        $testsByClass = $aSubmission->assessment->testsByClass();

        $parser = new JUnitXMLParser;
        /** @var BuildResultFile[] $junitFiles */
        $junitFiles = $aSubmission->submission->resultFiles()->where('source', BuildResultFile::SOURCE_JUNIT)->get();
        foreach ($junitFiles as $junitFile) {
            try {
                $junitSuite = $parser->parse($junitFile->unpackIntoTemporaryFile());

                foreach ($junitSuite->testCases as $tc) {
                    // Retrieve the points for the test and remove the entry from the pending tests
                    $testPoints = ($testsByClass[$tc->className] ?? [])[$tc->name] ?? null;

                    if ($tc->isPassed()) {
                        $aSubmission->passed += 1;
                        if (isset($testPoints)) {
                            $aSubmission->points = bcadd($aSubmission->points, $testPoints, 2);
                        }
                    }

                    $aSubmission->failed += $tc->failure ? 1 : 0;
                    $aSubmission->errors += $tc->error ? 1 : 0;
                    $aSubmission->skipped += $tc->skipped ? 1 : 0;
                }
            } catch (Exception $e) {
                Log::error("Failed to parse " . $junitFile->originalPath
                    . " while marking submission " . $this->aSubmissionId . ": " . $e);
            }
        }

        foreach ($aSubmission->missingTestSuites() as $testSuite) {
            $aSubmission->missing += count($testSuite->testCases);
        }

        $aSubmission->save();
        event(new SubmissionMarksUpdated($aSubmission));
    }
}
