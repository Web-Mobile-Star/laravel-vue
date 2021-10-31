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

use App\TeachingModuleUser;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

class TeachingModuleUserPolicy
{
    use HandlesAuthorization;

    /** @var string LIKE pattern used to find roles specifically for teaching model users. */
    const TMU_ROLE_LIKE = 'Module:%_';

    /** @var string Prefixed and ordered name for the role given to students within a module. */
    const STUDENT_ROLE = 'Module:01_Student';

    /** @var string Prefixed and ordered name for the role given to observers within a module. */
    const OBSERVER_ROLE = 'Module:05_Observer';

    /** @var string Prefixed and ordered name for the role given to teaching assistants within a module. */
    const TEACHING_ASSISTANT_ROLE = 'Module:10_Teaching Assistant';

    /** @var string Prefixed and ordered name for the role given to tutors within a module. */
    const TUTOR_ROLE = 'Module:15_Tutor';

    const ROLE_PERMISSIONS = [
        self::STUDENT_ROLE => [
            TeachingModulePolicy::VIEW_PERMISSION,
            TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION,
            AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION,
        ],
        self::OBSERVER_ROLE => [
            TeachingModulePolicy::VIEW_PERMISSION,
            self::VIEW_USERS_PERMISSION,
            TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION,
            AssessmentPolicy::VIEW_MODEL_SOLUTION_PERMISSION,
            AssessmentPolicy::VIEW_OVERRIDES_PERMISSION,
            AssessmentPolicy::VIEW_TESTS_PERMISSION,
            AssessmentPolicy::VIEW_PROGRESS_PERMISSION,
            ZipSubmissionPolicy::VIEW_ANY_PERMISSION,
        ],
        self::TEACHING_ASSISTANT_ROLE => [
            TeachingModulePolicy::VIEW_PERMISSION,
            self::ENROL_USERS_PERMISSION,
            self::VIEW_USERS_PERMISSION,
            TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION,
            TeachingModuleItemPolicy::VIEW_UNAVAILABLE_PERMISSION,
            AssessmentPolicy::VIEW_MODEL_SOLUTION_PERMISSION,
            AssessmentPolicy::VIEW_OVERRIDES_PERMISSION,
            AssessmentPolicy::VIEW_TESTS_PERMISSION,
            AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION,
            AssessmentPolicy::VIEW_PROGRESS_PERMISSION,
            AssessmentPolicy::COMPARE_PERMISSION,
            AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION,
            ZipSubmissionPolicy::VIEW_ANY_PERMISSION,
        ],
        self::TUTOR_ROLE => [
            TeachingModulePolicy::VIEW_PERMISSION,
            TeachingModulePolicy::UPDATE_PERMISSION,
            self::ENROL_USERS_PERMISSION,
            self::UPDATE_USERS_PERMISSION,
            self::VIEW_USERS_PERMISSION,
            self::REMOVE_USERS_PERMISSION,
            TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION,
            TeachingModuleItemPolicy::VIEW_UNAVAILABLE_PERMISSION,
            TeachingModuleItemPolicy::CREATE_PERMISSION,
            TeachingModuleItemPolicy::UPDATE_PERMISSION,
            TeachingModuleItemPolicy::DELETE_PERMISSION,
            AssessmentPolicy::UPLOAD_MODEL_SOLUTION_PERMISSION,
            AssessmentPolicy::VIEW_MODEL_SOLUTION_PERMISSION,
            AssessmentPolicy::DELETE_MODEL_SOLUTION_PERMISSION,
            AssessmentPolicy::MODIFY_OVERRIDES_PERMISSION,
            AssessmentPolicy::VIEW_OVERRIDES_PERMISSION,
            AssessmentPolicy::MODIFY_TESTS_PERMISSION,
            AssessmentPolicy::VIEW_TESTS_PERMISSION,
            AssessmentPolicy::UPLOAD_SUBMISSION_PERMISSION,
            AssessmentPolicy::UPLOAD_SUBMISSION_ON_BEHALF_OF_PERMISSION,
            AssessmentPolicy::VIEW_PROGRESS_PERMISSION,
            AssessmentPolicy::COMPARE_PERMISSION,
            AssessmentSubmissionPolicy::DELETE_SUBMISSION_PERMISSION,
            AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION,
            ZipSubmissionPolicy::VIEW_ANY_PERMISSION,
        ]
    ];

    public const REMOVE_USERS_PERMISSION = 'remove users from teaching module';
    public const UPDATE_USERS_PERMISSION = 'update users in teaching module';
    public const VIEW_USERS_PERMISSION = 'view users of teaching module';
    public const ENROL_USERS_PERMISSION = 'enrol users to teaching module';

    public const PERMISSIONS = [
        self::VIEW_USERS_PERMISSION,
        self::ENROL_USERS_PERMISSION,
        self::REMOVE_USERS_PERMISSION,
        self::UPDATE_USERS_PERMISSION,
    ];

    /**
     * Returns all the TeachingModuleUser-specific roles, in the expected order.
     * @return Role[]
     */
    public static function allRoles()
    {
        return Role::where('name', 'like', self::TMU_ROLE_LIKE)->orderBy('name');
    }

    /**
     * Cleans and translates one of the TeachingModuleUser-specific role anmes for the UI.
     * @param string $roleName Name of the role.
     * @return string Cleaned role.
     */
    public static function cleanRoleName(string $roleName): string
    {
        return __(explode('_', $roleName, 2)[1]);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param TeachingModuleUser $teachingModelUser
     * @return mixed
     */
    public function view(User $user, TeachingModuleUser $teachingModelUser)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $teachingModelUser->module, self::VIEW_USERS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }

        // TODO A user should be able to view own record
    }

    /**
     * Determine whether the authenticated user can view the list of submissions of the TMU.
     */
    public function viewSubmissions(User $user, TeachingModuleUser $tmu) {
        if ($user->can('viewSubmissions', $tmu->module)) {
            // The user can see all submissions in the module
            return true;
        }

        // TODO a user should be able to see own list of submissions
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param TeachingModuleUser $teachingModelUser
     * @return mixed
     */
    public function update(User $user, TeachingModuleUser $teachingModelUser)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $teachingModelUser->module, self::UPDATE_USERS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param TeachingModuleUser $teachingModelUser
     * @return mixed
     */
    public function delete(User $user, TeachingModuleUser $teachingModelUser)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $teachingModelUser->module, self::REMOVE_USERS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

}
