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

namespace Tests\Feature;

use App\TeachingModuleUser;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TMURoleSwitchingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * @param string[] $roles
     * @param User $user
     * @param int $moduleID
     * @param \Closure $request
     */
    protected function assertRolesForbid(array $roles, User $user, int $moduleID, \Closure $request): void
    {
        foreach ($roles as $r) {
            $this->switchRoleTo($user, $moduleID, $r);
            $response = $request();
            $response->assertForbidden();
        }
    }

    /**
     * @param TeachingModuleUser $tmu
     * @param string[] $forbiddenRoles
     * @param string[] $allowedRoles
     * @param \Closure $request
     * @param int $status
     */
    protected function assertRolesWork(TeachingModuleUser $tmu, array $forbiddenRoles, array $allowedRoles, \Closure $request, int $status = 302, bool $checkNoErrors=true): void
    {
        $moduleID = $tmu->teaching_module_id;
        $user = $tmu->user()->first();

        $tmu->assignRole($forbiddenRoles);
        $tmu->assignRole($allowedRoles);
        $tmu->refresh();
        $this->assertEquals(count($forbiddenRoles) + count($allowedRoles), $tmu->roles()->count());

        $this->assertRolesForbid($forbiddenRoles, $user, $moduleID, $request);
        foreach ($allowedRoles as $r) {
            $this->switchRoleTo($user, $moduleID, $r);
            $response = $request();
            $response->assertStatus($status);
            if ($checkNoErrors) {
                $response->assertSessionHasNoErrors();
            }
        }
    }

    /**
     * @param User $user
     * @param int $moduleID
     * @param string $role
     * @return TestResponse
     */
    protected function switchRoleTo(User $user, int $moduleID, string $role): TestResponse
    {
        $response = $this->actingAs($user)->post(route('modules.switchRole', $moduleID), [
            'role' => Role::where('name', $role)->first()->id
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        return $response;
    }
}
