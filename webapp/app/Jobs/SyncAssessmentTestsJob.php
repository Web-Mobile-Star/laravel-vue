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

use App\Assessment;
use App\AssessmentTest;
use App\BuildResultFile;
use App\JUnit\JUnitXMLParser;
use App\ModelSolution;
use App\ZipSubmission;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use function GuzzleHttp\Psr7\copy_to_stream;

class SyncAssessmentTestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \App\Assessment
     */
    private $assessment;

    /**
     * Create a new job instance.
     *
     * @param \App\Assessment $assessment
     */
    public function __construct(\App\Assessment $assessment)
    {
        $this->onQueue(ZipSubmission::QUEUE_NON_JAVA_HIGH);
        $this->assessment = $assessment;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        Log::info("Synchronizing tests for model solution of assessment " . $this->assessment->id);

        DB::transaction(function() {
            $this->assessment->load('latestModelSolution');
            $latestModelSolution = $this->assessment->latestModelSolution;
            $modelSolution = $latestModelSolution->submission;

            /** @var Collection $junitFiles */
            $junitFiles = $modelSolution->resultFiles()->where('source', BuildResultFile::SOURCE_JUNIT)->get();

            /** @var BuildResultFile $junitFile */
            $tmpJUnitXMLFile = tempnam(sys_get_temp_dir(), 'junit');
            $junitParser = new JUnitXMLParser();
            $testClasses = [];

            foreach ($junitFiles as $junitFile) {
                $tmpFile = $junitFile->unpackIntoTemporaryFile();
                try {
                    $junitSuite = $junitParser->parse($tmpFile);

                    $testClasses[$junitSuite->name] = 1;
                    $testNames = [];
                    foreach ($junitSuite->testCases as $tc) {
                        $testNames[] = $tc->name;
                        AssessmentTest::firstOrCreate([
                            'class_name' => $junitSuite->name,
                            'name' => $tc->name,
                            'assessment_id' => $this->assessment->id,
                        ], [
                            'points' => '0.00',
                            'feedback_markdown' => '',
                        ]);
                    }

                    // Remove test methods that do not exist anymore in this class
                    $this->assessment->tests()
                        ->where('class_name', $junitSuite->name)
                        ->whereNotIn('name', $testNames)->delete();
                } catch (Exception $e) {
                    Log::error("Failed to parse " . $junitFile->originalPath
                        . " while synchronising the tests for assessment " . $this->assessment->id
                        . ": " . $e);
                }
            }

            unlink($tmpJUnitXMLFile);

            // Remove test classes that do not exist anymore
            $testclass_names = array_keys($testClasses);
            $this->assessment->tests()->whereNotIn('class_name', $testclass_names)->delete();
        });

    }
}
