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

namespace App\Events;

use App\AssessmentSubmission;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SubmissionMarksUpdated implements ShouldBroadcast
{
    /**
     * @var AssessmentSubmission
     */
    public $submission;

    /**
     * Create a new event instance.
     *
     * @param AssessmentSubmission $submission
     */
    public function __construct(AssessmentSubmission $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('asub.' . $this->submission->id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith() {
        return [
            'id' => $this->submission->id,
            'points' => $this->submission->points,
            'passed' => $this->submission->passed,
            'failed' => $this->submission->failed,
            'errors' => $this->submission->errors,
            'skipped' => $this->submission->skipped,
            'missing' => $this->submission->missing,
        ];
    }

}
