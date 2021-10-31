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

use App\Assessment;
use App\TeachingModule;
use App\User;
use App\ZipSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentPolicy
{
    use HandlesAuthorization;

    const UPLOAD_MODEL_SOLUTION_PERMISSION = 'can upload model solution for assessment';
    const VIEW_MODEL_SOLUTION_PERMISSION = 'can view model solution for assessment';
    const DELETE_MODEL_SOLUTION_PERMISSION = 'can delete model solution for assessment';

    const MODIFY_OVERRIDES_PERMISSION = 'can modify file overrides for assessment';
    const VIEW_OVERRIDES_PERMISSION = 'can view file overrides for assessment';

    const MODIFY_TESTS_PERMISSION = 'can modify tests for assessment';
    const VIEW_TESTS_PERMISSION = 'can view tests for assessment';

    const UPLOAD_SUBMISSION_PERMISSION = 'can upload submission to assessment';
    const UPLOAD_SUBMISSION_ON_BEHALF_OF_PERMISSION = 'can upload submission on behalf of other module user to assessment';

    const VIEW_PROGRESS_PERMISSION = 'can view progress of cohort';

    const COMPARE_PERMISSION = 'can compare assessment submissions with external submissions';

    const PERMISSIONS = [
        self::UPLOAD_MODEL_SOLUTION_PERMISSION,
        self::VIEW_MODEL_SOLUTION_PERMISSION,
        self::DELETE_MODEL_SOLUTION_PERMISSION,
        self::MODIFY_OVERRIDES_PERMISSION,
        self::VIEW_OVERRIDES_PERMISSION,
        self::MODIFY_TESTS_PERMISSION,
        self::VIEW_TESTS_PERMISSION,
        self::UPLOAD_SUBMISSION_PERMISSION,
        self::UPLOAD_SUBMISSION_ON_BEHALF_OF_PERMISSION,
        self::VIEW_PROGRESS_PERMISSION,
        self::COMPARE_PERMISSION,
    ];

    /**
     * Determine whether the user can view the model solution for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function viewModelSolution(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::VIEW_MODEL_SOLUTION_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can upload a model solution for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function uploadModelSolution(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::UPLOAD_MODEL_SOLUTION_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can delete the model solution for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function deleteModelSolution(User $user, Assessment $assessment)
    {
        $latestMS = $assessment->latestModelSolution;
        if ($latestMS && $latestMS->assessmentSubmissions()->exists()) {
            // You cannot delete a model solution that has assessment submissions depending on it
            return false;
        }

        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::DELETE_MODEL_SOLUTION_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can view the file overrides for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function viewOverrides(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::VIEW_OVERRIDES_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can modify the file overrides for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function modifyOverrides(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::MODIFY_OVERRIDES_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can view the tests for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function viewTests(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::VIEW_TESTS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can modify the tests for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function modifyTests(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::MODIFY_TESTS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can upload a submission for an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function uploadSubmission(User $user, Assessment $assessment)
    {
        return $this->assessmentAvailableWithPermission(
            $assessment, $user, self::UPLOAD_SUBMISSION_PERMISSION);
    }

    /**
     * Determine whether the user can upload a submission for an assessment on behalf of another user.
     * @param User $user
     * @param Assessment $assessment
     * @return bool
     */
    public function uploadSubmissionOnBehalfOf(User $user, Assessment $assessment)
    {
        return $this->assessmentAvailableWithPermission(
            $assessment, $user, self::UPLOAD_SUBMISSION_ON_BEHALF_OF_PERMISSION);
    }

    /**
     * Determine whether the user can visualize the progress of the cohort.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return mixed
     */
    public function viewProgress(User $user, Assessment $assessment)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, self::VIEW_PROGRESS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can visualize the submissions made to an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return bool
     */
    public function viewSubmissions(User $user, Assessment $assessment) {
        return $user->can('viewSubmissions', $assessment->usage->module);
    }

    /**
     * Determine whether the user can rerun the submissions made to an assessment.
     * @param User $user
     * @param Assessment $assessment
     * @return bool
     */
    public function rerunSubmissions(User $user, Assessment $assessment) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, AssessmentSubmissionPolicy::RERUN_SUBMISSION_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can compare the submissions made to an assessment, with those from an external
     * repository.
     * @param User $user
     * @param Assessment $assessment
     */
    public function compare(User $user, Assessment $assessment) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, AssessmentPolicy::COMPARE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Checks that the assessment is within the specifieid module.
     *
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @throws AuthorizationException
     */
    public static function authorizeNesting(TeachingModule $module, Assessment $assessment): void
    {
        if ($assessment->usage->teaching_module_id != $module->id) {
            throw new AuthorizationException("The assessment must belong to the specified module.");
        }
    }

    /**
     * @param Assessment $assessment
     * @param User $user
     * @param string $permission
     */
    private function assessmentAvailableWithPermission(Assessment $assessment, User $user, string $permission)
    {
        if (is_null($assessment->latestModelSolution)) {
            // You cannot upload a submission if there is no model submission
            return false;
        }
        if ($user->can('view', $assessment->usage) === false) {
            // You cannot upload a submission if you cannot view the module item
            return false;
        }

        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $assessment->usage->module, $permission);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }
}
