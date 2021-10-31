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

use App\Folder;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FolderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view available items inside this module.
     *
     * @param User $user
     * @param Folder $folder
     * @return mixed
     */
    public function viewAvailableItems(User $user, Folder $folder) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $folder->usage->module, TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }

    /**
     * Determine whether the user can view all items inside this module.
     *
     * @param User $user
     * @param Folder $folder
     * @return mixed
     */
    public function viewUnavailableItems(User $user, Folder $folder) {
        $hasPermission = TeachingModulePolicy::hasPermissionTo($user,
            $folder->usage->module, TeachingModuleItemPolicy::VIEW_UNAVAILABLE_PERMISSION);
        if (!is_null($hasPermission)) {
            return $hasPermission;
        }
    }
}
