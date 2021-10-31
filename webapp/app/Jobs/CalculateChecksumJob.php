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

namespace App\Jobs;

use App\ZipSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CalculateChecksumJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int ID of the {@link ZipSubmission} which should have its checksums computed.
     */
    private $submissionId;

    /**
     * Create a new job instance.
     *
     * @param int $subId ID of the {@link ZipSubmission} which should have its checksums computed.
     */
    public function __construct(int $subId)
    {
        $this->onQueue(ZipSubmission::QUEUE_NON_JAVA_LOW);
        $this->submissionId = $subId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /* @var ZipSubmission $submission */
        $submission = ZipSubmission::find($this->submissionId);
        if (!$submission) {
            Log::warn('Submission #' . $this->submissionId . ' no longer exists, aborting checksum');
            return;
        }

        Log::info('Computing checksum for submission #' . $this->submissionId);
        $readStream = Storage::readStream($submission->diskPath);
        if ($readStream) {
            try {
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $readStream);
                $submission->sha256 = hash_final($ctx);
                $submission->save();
                Log::info('Computed checksum for submission #' . $this->submissionId);
            } finally {
                fclose($readStream);
            }
        } else {
            Log::error('Could not fetch contents of submission #' . $this->submissionId . ' for checksum');
        }
    }

}
