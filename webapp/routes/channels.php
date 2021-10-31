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

use App\AssessmentSubmission;
use App\User;
use App\ZipSubmission;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('job.{jobId}', function (User $user, $jobId) {
    /** @var ZipSubmission $job */
    $job = ZipSubmission::find($jobId);
    return $user->id === $job->user_id || $user->isAdmin();
});

Broadcast::channel('asub.{asubId}', function (User $user, $asubId) {
    /** @var AssessmentSubmission $asub */
    $asub = AssessmentSubmission::find($asubId);
    return $asub->author->user_id === $user->id || $user->can('view', $asub) || $user->isAdmin();
});
