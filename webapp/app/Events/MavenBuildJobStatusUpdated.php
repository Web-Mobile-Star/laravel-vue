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

use App\ZipSubmission;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MavenBuildJobStatusUpdated implements ShouldBroadcast {

    /**
     * @var ZipSubmission Job whose status changed.
     */
    public $job;

    /**
     * Creates a new instance of this event.
     * @param ZipSubmission $job Job whose status changed.
     */
    public function __construct(ZipSubmission $job)
    {
        $this->job = $job;
    }

     /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\PrivateChannel
     */
    public function broadcastOn()
    {
        return new PrivateChannel('job.' . $this->job->id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith() {
        return [
            'id' => $this->job->id,
            'status' => $this->job->status,
            'statusString' => $this->job->statusString(),
            'filename' => $this->job->filename,
            'createdAt' => $this->job->created_at,
            'updatedAt' => $this->job->updated_at,
            'user' => [
                'id' => $this->job->user_id,
                'name' => $this->job->user->name,
                'email' => $this->job->user->email
            ]
        ];
    }

}
