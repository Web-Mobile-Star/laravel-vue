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

use App\TeachingModule;
use App\TeachingModuleUser;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class TeachingModulePolicy
{
    use HandlesAuthorization;

    // This is mostly for admin types
    const CREATE_PERMISSION = 'create teaching module';

    /*
     * These can be given to the User (for all modules), or to a
     * role in a specific TeachingModuleUser (restricted to a
     * specific module, user can pick which role to enter the
     * module as).
     */
    const VIEW_PERMISSION = 'view teaching module';
    const UPDATE_PERMISSION = 'update teaching module';
    const DELETE_PERMISSION = 'delete teaching module';

    const PERMISSIONS = [
        self::VIEW_PERMISSION,
        self::CREATE_PERMISSION,
        self::UPDATE_PERMISSION,
        self::DELETE_PERMISSION,
    ];

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        if ($user->hasPermissionTo(self::VIEW_PERMISSION)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param \App\User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function view(User $user, TeachingModule $module)
    {
        $hasPermission = self::hasPermissionTo($user, $module, self::VIEW_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
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
        if ($user->hasPermissionTo(self::CREATE_PERMISSION)) {
            return true;
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param \App\User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function update(User $user, TeachingModule $module)
    {
        $hasPermission = self::hasPermissionTo($user, $module, self::UPDATE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }

        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param \App\User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function delete(User $user, TeachingModule $module)
    {
        $hasPermission = self::hasPermissionTo($user, $module, self::DELETE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }

        //
    }

    /**
     * Returns the ID of the currently active role, if any.
     * @param User $user
     * @param TeachingModule $module
     * @return mixed|null
     */
    public static function getActiveRole(User $user, TeachingModule $module)
    {
        /** @var TeachingModuleUser $authModuleUser */
        $authModuleUser = $user->moduleUser($module);

        $roleID = session('activeRole.' . $module->id);
        if (!is_null($roleID) && !is_null($authModuleUser)) {
            return $authModuleUser->roles->where('id', $roleID)->first();
        }

        // If you are not a super admin, you have the strongest (last)
        // role active by default.
        if (!$user->isAdmin() && !is_null($authModuleUser)) {
            // Use cached roles relation instead of repeatedly doing queries
            $roles = [];
            foreach ($authModuleUser->roles as $r) {
                $roles[] = $r;
            }
            usort($roles, function ($rA, $rB) {
                return -strcmp($rA->name, $rB->name);
            });

            return (count($roles) > 0) ? $roles[0] : null;
        }

        // No default role: not a user in the module, or you are a superuser
        return null;
    }

    /**
     * Sets the ID of the currently active role.
     * @param TeachingModule $module
     * @param ?Role $role
     */
    public static function setActiveRole(TeachingModule $module, ?Role $role) {
        $sessionKey = 'activeRole.' . $module->id;
        session([$sessionKey => $role ? $role->id : null]);
    }

    /**
     * Determine whether the user can view the users assigned to this module.
     * @param User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function viewUsers(User $user, TeachingModule $module) {
        $hasPermission = self::hasPermissionTo($user, $module, TeachingModuleUserPolicy::VIEW_USERS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can enrol users to this module.
     * @param User $user
     * @param TeachingModule $teachingModule
     * @return mixed
     */
    public function enrolUsers(User $user, TeachingModule $module) {
        $hasPermission = self::hasPermissionTo($user, $module, TeachingModuleUserPolicy::ENROL_USERS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can remove users from this module.
     * @param User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function removeUsers(User $user, TeachingModule $module) {
        $hasPermission = self::hasPermissionTo($user, $module, TeachingModuleUserPolicy::REMOVE_USERS_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can create items inside this module.
     *
     * @param User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function createItem(User $user, TeachingModule $module)
    {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user, $module, TeachingModuleItemPolicy::CREATE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can view available items inside this module.
     *
     * @param User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function viewAvailableItems(User $user, TeachingModule $module) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user, $module, TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can view all items inside this module.
     *
     * @param User $user
     * @param TeachingModule $module
     * @return mixed
     */
    public function viewUnavailableItems(User $user, TeachingModule  $module) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user, $module, TeachingModuleItemPolicy::VIEW_UNAVAILABLE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can view all submissions for the module.
     */
    public function viewSubmissions(User $user, TeachingModule $module) {
        /*
         * Either the user directly has the "view any submission" permission (global to the server),
         * or their enrolment to the module has it (specific to the module).
         */
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $module, ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    public static function hasPermissionTo(User $user, TeachingModule $module, string $permission) {
        // Your active role may have that permission
        $activeRole = self::getActiveRole($user, $module);
        if ($activeRole && $activeRole->hasPermissionTo($permission)) {
            return true;
        }

        // Your module enrolment (if any) may have that direct permission (outside roles)
        if ($user->moduleUser($module) && $user->moduleUser($module)->hasDirectPermission($permission)) {
            return true;
        }

        // Your general user account may have that permission across all modules
        if ($user->hasPermissionTo($permission)) {
            return true;
        }
    }

}
