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

use App\TeachingModuleItem;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\User;

class TeachingModuleItemPolicy
{
    use HandlesAuthorization;

    const CREATE_PERMISSION = 'create module item';
    const UPDATE_PERMISSION = 'update module item';
    const DELETE_PERMISSION = 'delete module item';
    const VIEW_AVAILABLE_PERMISSION = 'view available module item';
    const VIEW_UNAVAILABLE_PERMISSION = 'view unavailable module item';

    const PERMISSIONS = [
        self::CREATE_PERMISSION,
        self::UPDATE_PERMISSION,
        self::DELETE_PERMISSION,
        self::VIEW_AVAILABLE_PERMISSION,
        self::VIEW_UNAVAILABLE_PERMISSION,
    ];

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param TeachingModuleItem $teachingModuleItem
     * @return mixed
     */
    public function view(User $user, TeachingModuleItem $teachingModuleItem)
    {
        $neededPerm = $teachingModuleItem->isAvailable()
            ? self::VIEW_AVAILABLE_PERMISSION : self::VIEW_UNAVAILABLE_PERMISSION;

        $perm = TeachingModulePolicy::hasPermissionTo($user, $teachingModuleItem->module, $neededPerm);
        if (!is_null($perm)) {
            return $perm;
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param TeachingModuleItem $teachingModuleItem
     * @return mixed
     */
    public function update(User $user, TeachingModuleItem $teachingModuleItem)
    {
        $perm = TeachingModulePolicy::hasPermissionTo($user, $teachingModuleItem->module, self::UPDATE_PERMISSION);
        if (!is_null($perm)) {
            return $perm;
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param TeachingModuleItem $teachingModuleItem
     * @return mixed
     */
    public function delete(User $user, TeachingModuleItem $teachingModuleItem)
    {
        $perm = TeachingModulePolicy::hasPermissionTo($user, $teachingModuleItem->module, self::DELETE_PERMISSION);
        if (!is_null($perm)) {
            return $perm;
        }
    }

}
