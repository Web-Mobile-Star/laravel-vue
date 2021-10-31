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

namespace App\Console\Commands;

use App\Assessment;
use App\AssessmentSubmission;
use App\Jobs\CalculateChecksumJob;
use App\ZipSubmission;
use Illuminate\Console\Command;

class ChecksumAssessment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autofeedback:checksum-assessment {aID : ID of the assessment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedules checksum calculation for all submissions of an assessment without a checksum';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $assessmentId = $this->argument('aID');

        /* @var \App\Assessment|null $assessment */
        $assessment = Assessment::find($assessmentId);
        if (!$assessment) {
            $this->error("Assessment ID #${assessmentId} not found");
            return 1;
        }

        $scheduled = 0;
        /** @var AssessmentSubmission $asub */
        foreach ($assessment->submissions()->with('submission')->get() as $asub) {
            if ($asub->submission->sha256 == ZipSubmission::SHA256_PENDING) {
                CalculateChecksumJob::dispatch($asub->submission->id);
                $scheduled++;
            }
        }
        $this->info("Scheduled ${scheduled} jobs for assessment #${assessmentId}");

        return 0;
    }
}
