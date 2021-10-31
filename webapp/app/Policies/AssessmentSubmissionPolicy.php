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

namespace App\Policies;

use App\AssessmentSubmission;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentSubmissionPolicy
{
    use HandlesAuthorization;

    const DELETE_SUBMISSION_PERMISSION = 'can delete submission to assessment';
    const RERUN_SUBMISSION_PERMISSION = 'can rerun any submission to assessment';

    const PERMISSIONS = [
        self::DELETE_SUBMISSION_PERMISSION,
        self::RERUN_SUBMISSION_PERMISSION,
    ];

    /**
     * Determine whether the user can view the model.
     * @param User $user
     * @param AssessmentSubmission $assessmentSubmission
     * @return mixed
     */
    public function view(User $user, AssessmentSubmission $assessmentSubmission) {
        if ($assessmentSubmission->author->user_id == $user->id) {
            // Users can always see their own submissions *if they can also see the item*
            if ($user->can('view', $assessmentSubmission->assessment->usage)) {
                return true;
            }
        }

        // Does the user have permission to see any submission in the module?
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessmentSubmission->assessment->usage->module,
            ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param AssessmentSubmission $assessmentSubmission
     * @return mixed
     */
    public function delete(User $user, AssessmentSubmission $assessmentSubmission)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessmentSubmission->assessment->usage->module, self::DELETE_SUBMISSION_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    public function rerun(User $user, AssessmentSubmission $asub) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $asub->assessment->usage->module, self::RERUN_SUBMISSION_PERMISSION);
        if (!is_null($hasPermission)) {
            // The TMU has the permission to rerun any submissions
            return $hasPermission;
        }

        if ($asub->author->user_id == $user->id
            && $asub->updated_at->isBefore($asub->assessment->latestModelSolution->submission->updated_at)) {
            // The author can always rerun their own submission if it is outdated
            return true;
        }
    }
}
