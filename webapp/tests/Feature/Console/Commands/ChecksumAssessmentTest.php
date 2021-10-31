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

namespace Tests\Feature\Console\Commands;

use App\AssessmentSubmission;
use App\Jobs\CalculateChecksumJob;
use App\ZipSubmission;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChecksumAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function testRunNoPending() {
        Storage::fake();
        Queue::fake();

        /* @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        Artisan::call('autofeedback:checksum-assessment', ['aID' => $asub->assessment->id]);
        Queue::assertPushed(CalculateChecksumJob::class, 0);
    }

    public function testRunOnePending() {
        Storage::fake();
        Queue::fake();

        /* @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        $asub->submission->sha256 = ZipSubmission::SHA256_PENDING;
        $asub->submission->save();

        Artisan::call('autofeedback:checksum-assessment', ['aID' => $asub->assessment->id]);
        Queue::assertPushed(CalculateChecksumJob::class, 1);
    }

}
