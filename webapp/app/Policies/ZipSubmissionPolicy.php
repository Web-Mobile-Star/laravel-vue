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

use App\User;
use App\ZipSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ZipSubmissionPolicy
{
    use HandlesAuthorization;

    const VIEW_ANY_PERMISSION = 'view any submission';
    const UPDATE_ANY_PERMISSION = 'update any submission';
    const DELETE_ANY_PERMISSION = 'delete any submission';

    /**
     * @var string Name of the permission for creating raw submissions, e.g. submissions
     * not associated to a specific assignment.
     */
    const CREATE_RAW_PERMISSION = 'create raw submission';

    /**
     * @var string[] List of all the permissions used by this policy.
     */
    const PERMISSIONS = [
        self::VIEW_ANY_PERMISSION,
        self::UPDATE_ANY_PERMISSION,
        self::DELETE_ANY_PERMISSION,
        self::CREATE_RAW_PERMISSION,
    ];

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        if ($user->can(self::VIEW_ANY_PERMISSION)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\ZipSubmission  $zipSubmission
     * @return mixed
     */
    public function view(User $user, ZipSubmission $zipSubmission)
    {
        if ($this->viewAny($user)) {
            return true;
        }

        if ($zipSubmission->modelSolution) {
            // Model solution for a submission
            $module = $zipSubmission->modelSolution->assessment->usage->module;
            $hasPerm = TeachingModulePolicy::hasPermissionTo($user, $module,
                AssessmentPolicy::VIEW_MODEL_SOLUTION_PERMISSION);
            if (!is_null($hasPerm)) {
                return $hasPerm;
            }
        }

        if ($zipSubmission->user->id == $user->id) {
            // A user can always see what they submitted
            return true;
        }

        if ($zipSubmission->assessment) {
            $module = $zipSubmission->assessment->assessment->usage->module;
            $hasPerm = TeachingModulePolicy::hasPermissionTo($user, $module,
                ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

            if (!is_null($hasPerm)) {
                return $hasPerm;
            }
        }
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if ($user->can(self::CREATE_RAW_PERMISSION)) {
            return true;
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\ZipSubmission  $zipSubmission
     * @return mixed
     */
    public function update(User $user, ZipSubmission $zipSubmission)
    {
        if ($user->can(self::UPDATE_ANY_PERMISSION)) {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\ZipSubmission  $zipSubmission
     * @return mixed
     */
    public function delete(User $user, ZipSubmission $zipSubmission)
    {
        if ($zipSubmission->modelSolution || $zipSubmission->assessment) {
            // You may not delete files which are used as a model solution or as a submission:
            // either clear the model solution, or delete the submission instead.
            return false;
        }

        if ($user->can(self::DELETE_ANY_PERMISSION)) {
            return true;
        }
    }

}
