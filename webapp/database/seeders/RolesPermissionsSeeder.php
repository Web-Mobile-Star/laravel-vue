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

namespace Database\Seeders;

use App\Policies\AssessmentPolicy;
use App\Policies\AssessmentSubmissionPolicy;
use App\Policies\BuildResultFilePolicy;
use App\Policies\TeachingModuleItemPolicy;
use App\Policies\TeachingModulePolicy;
use App\Policies\TeachingModuleUserPolicy;
use App\Policies\ZipSubmissionPolicy;
use App\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Super user role (automatically has all permissions)
        Role::findOrCreate(User::SUPER_ADMIN_ROLE);

        // Model policy permissions
        $this->findOrCreatePermissions(ZipSubmissionPolicy::PERMISSIONS);
        $this->findOrCreatePermissions(TeachingModulePolicy::PERMISSIONS);
        $this->findOrCreatePermissions(TeachingModuleItemPolicy::PERMISSIONS);
        $this->findOrCreatePermissions(TeachingModuleUserPolicy::PERMISSIONS);
        $this->findOrCreatePermissions(AssessmentPolicy::PERMISSIONS);
        $this->findOrCreatePermissions(AssessmentSubmissionPolicy::PERMISSIONS);

        // Roles for teaching module users
        foreach (TeachingModuleUserPolicy::ROLE_PERMISSIONS as $role => $permissions) {
            /** @var Role $r */
            $r = Role::findOrCreate($role);
            $r->syncPermissions($permissions);
        }
    }

    private function findOrCreatePermissions($perms) {
        foreach ($perms as $perm) {
            Permission::findOrCreate($perm);
        }
    }
}
