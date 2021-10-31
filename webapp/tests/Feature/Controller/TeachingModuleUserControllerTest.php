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

namespace Tests\Feature\Controller;

use App\Assessment;
use App\AssessmentSubmission;
use App\Http\Controllers\SubmissionsTable;
use App\Policies\TeachingModuleUserPolicy;
use App\Policies\ZipSubmissionPolicy;
use App\TeachingModule;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\User;
use Database\Seeders\DevelopmentUserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Spatie\Permission\Models\Role;
use Tests\Feature\TMURoleSwitchingTestCase;

class TeachingModuleUserControllerTest extends TMURoleSwitchingTestCase
{
    public function tearDown(): void
    {
        // Needed due to https://github.com/DirectoryTree/LdapRecord-Laravel/issues/230
        DirectoryEmulator::tearDown();

        parent::tearDown();
    }

    public function testViewUsersForbidden()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.index', $module->id));
        $response->assertForbidden();
    }

    public function testViewUsersDirectPermissionNoUsers()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::VIEW_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.index', $module->id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.users.index');
        $this->assertEquals(0, $response->viewData('users')->total());
        $response->assertViewHas('module');
    }

    public function testViewUsersDirectPermissionSomeUsers()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::VIEW_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        TeachingModuleUser::factory()->count(3)->create([
            'teaching_module_id' => $module->id
        ]);

        $response = $this->actingAs($user)->get(route('modules.users.index', $module->id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.users.index');
        $response->assertViewHas('users');
        $response->assertViewHas('module');

        $this->assertEquals(3, $response->viewData('users')->total());
    }

    public function testViewUsersRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $request = function() use ($tmu) {
            /** @var User $user */
            $user = $tmu->user()->first();
            /** @var TeachingModule $module */
            $module = $tmu->module()->first();
            return $this->actingAs($user)->get(route('modules.users.index', $module->id));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE
        ], [
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE
        ], $request, 200);
    }

    public function testCreateForbidden()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.create', $module->id));
        $response->assertForbidden();
    }

    public function testCreateDirectPermission()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::ENROL_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.create', $module->id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.users.create');
        $response->assertViewHas('module');
        $response->assertViewHas('roles');
    }

    public function testCreateRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $request = function() use ($tmu) {
            /** @var User $user */
            $user = $tmu->user()->first();
            /** @var TeachingModule $module */
            $module = $tmu->module()->first();
            return $this->actingAs($user)->get(route('modules.users.create', $module->id));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
        ], [
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testStoreForbidden()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => $user->email,
            'roles' => [
                Role::where('name', TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE)->first()->id,
                Role::where('name', TeachingModuleUserPolicy::TUTOR_ROLE)->first()->id,
            ]
        ]);
        $response->assertForbidden();
    }

    public function testStoreDirectPermission()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::ENROL_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $roleTA = Role::where('name', TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE)->firstOrFail();
        $roleTutor = Role::where('name', TeachingModuleUserPolicy::TUTOR_ROLE)->firstOrFail();

        // Valid query
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => $user->email,
            'roles' => [$roleTA->id, $roleTutor->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::where([
            'teaching_module_id' => $module->id,
            'user_id' => $user->id
        ])->firstOrFail();

        $this->assertEquals(2, $tmu->roles()->count());
        $this->assertTrue($tmu->hasRole($roleTA->name));
        $this->assertTrue($tmu->hasRole($roleTutor->name));

        // Do it again: it should complain saying the user is already enrolled
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => $user->email,
            'roles' => [$roleTA->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        $tmu->refresh();
        $this->assertEquals(2, $tmu->roles()->count());
    }

    public function testStoreDirectPermissionValidation()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::ENROL_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        $roleTA = Role::where('name', TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE)->firstOrFail();

        // not an email
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => 'notanemail', 'roles' => [$roleTA->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertEquals(0, TeachingModuleUser::count());

        // empty email
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => '', 'roles' => [$roleTA->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertEquals(0, TeachingModuleUser::count());

        // not the email of a user
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => 'missing@example.com', 'roles' => [$roleTA->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertEquals(0, TeachingModuleUser::count());

        // no roles
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => $user->email, 'roles' => []
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('roles');
        $this->assertEquals(0, TeachingModuleUser::count());

        // not a teaching module user role
        $randomRole = Role::create(['name' => 'randomRole']);
        $response = $this->actingAs($user)->post(route('modules.users.store', $module->id), [
            'email' => $user->email, 'roles' => [$randomRole->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('roles.0');
        $this->assertEquals(0, TeachingModuleUser::count());
    }

    public function testStoreRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        /** @var Role $roleStudent */
        $roleStudent = Role::where('name', TeachingModuleUserPolicy::STUDENT_ROLE)->first();

        $request = function() use ($tmu, $roleStudent) {
            /** @var User $user */
            $user = $tmu->user()->first();
            /** @var TeachingModule $module */
            $module = $tmu->module()->first();
            /** @var User $newUser */
            $newUser = User::factory()->create();

            return $this->actingAs($user)->post(route('modules.users.store', $module->id), [
                'email' => $newUser->email, 'roles' => [$roleStudent->id]
            ]);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
        ], [
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request);
    }

    public function testShowForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.show', [
            'module' => $tmu->teaching_module_id,
            'user' => $tmu->id
        ]));
        $response->assertForbidden();
    }

    public function testShowDirectPermission() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::VIEW_USERS_PERMISSION);
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $request = function () use ($user, $tmu) {
            return $this->actingAs($user)->get(route('modules.users.show', [
                'module' => $tmu->teaching_module_id,
                'user' => $tmu->id
            ]));
        };

        $response = $request();
        $response->assertSuccessful();
        $response->assertViewIs('modules.users.show');
        $response->assertViewHas('module');
        $response->assertViewHas('moduleUser');
        $response->assertDontSee('not made any submissions');

        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);
        $response = $request();
        $response->assertSuccessful();
        $response->assertSee('not made any submissions');
    }

    public function testShowWithSubmissions() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::VIEW_USERS_PERMISSION);
        $user->givePermissionTo(ZipSubmissionPolicy::VIEW_ANY_PERMISSION);

        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id,
        ]);

        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        $assessment->usage()->save($tmi);

        // Create 5 attempts for the same assessment
        /** @var AssessmentSubmission[] $asubs */
        for ($i = 0; $i < 5; $i++) {
            AssessmentSubmission::factory()->create([
                'teaching_module_user_id' => $tmu->id,
                'assessment_id' => $assessment->id,
                'attempt' => $i
            ]);
        }

        $request = function ($args = []) use ($user, $tmu) {
            return $this->actingAs($user)->get(route('modules.users.show', array_merge([
                'module' => $tmu->teaching_module_id,
                'user' => $tmu->id
            ], $args)));
        };

        $response = $request();
        $response->assertSuccessful();
        $this->assertEquals(5, $response->viewData('submissions')->submissions->count());

        // Sorting by attempt in descending order
        $response = $request([
            SubmissionsTable::SORT_BY_QUERY_KEY => 'attempt',
            SubmissionsTable::SORT_ORDER_QUERY_KEY => 'desc'
        ]);
        $response->assertSuccessful();
        $this->assertEquals(4, $response->viewData('submissions')->submissions[0]->attempt);

        // Smoke test for sorting by assessment
        $response = $request([
            SubmissionsTable::SORT_BY_QUERY_KEY => 'assessment'
        ]);
        $response->assertSuccessful();

        // Only view latest attempts
        $response = $request([
            SubmissionsTable::SHOW_LATEST_KEY => true,
        ]);
        $this->assertEquals(1, $response->viewData('submissions')->submissions->count());
    }

    public function testShowRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleUser $tmuOther */
        $tmuOther = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);
        $tmuOther->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);

        $request = function() use ($tmu, $tmuOther) {
            /** @var User $user */
            $user = $tmu->user()->first();

            return $this->actingAs($user)->get(route('modules.users.show', [
                'module' => $tmu->teaching_module_id,
                'user' => $tmuOther->id
            ]));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
        ], [
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testEditForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.edit', [
            'module' => $tmu->teaching_module_id,
            'user' => $tmu->id
        ]));
        $response->assertForbidden();
    }

    public function testEditDirectPermission() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::UPDATE_USERS_PERMISSION);
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.users.edit', [
            'module' => $tmu->teaching_module_id,
            'user' => $tmu->id
        ]));
        $response->assertSuccessful();
        $response->assertViewIs('modules.users.edit');
        $response->assertViewHas(['module', 'moduleUser', 'roles']);
    }

    public function testEditRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleUser $tmuOther */
        $tmuOther = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);
        $tmuOther->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);

        $request = function() use ($tmu, $tmuOther) {
            /** @var User $user */
            $user = $tmu->user()->first();

            return $this->actingAs($user)->get(route('modules.users.edit', [
                'module' => $tmu->teaching_module_id,
                'user' => $tmuOther->id
            ]));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testUpdateForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $roleTA = Role::where('name', TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE)->firstOrFail();
        $roleTutor = Role::where('name', TeachingModuleUserPolicy::TUTOR_ROLE)->firstOrFail();

        $response = $this->actingAs($user)->put(route('modules.users.update', [
            'module' => $tmu->teaching_module_id,
            'user' => $tmu->id
        ]), [
            'roles' => [$roleTA->id, $roleTutor->id]
        ]);
        $response->assertForbidden();
        $this->assertEquals(0, $tmu->roles()->count());
    }

    public function testUpdateDirectPermission() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::UPDATE_USERS_PERMISSION);
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $roleTA = Role::where('name', TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE)->firstOrFail();
        $roleTutor = Role::where('name', TeachingModuleUserPolicy::TUTOR_ROLE)->firstOrFail();

        $response = $this->actingAs($user)->put(route('modules.users.update', [
            'module' => $tmu->teaching_module_id,
            'user' => $tmu->id
        ]), [
            'roles' => [$roleTA->id, $roleTutor->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(2, $tmu->roles()->count());
        $this->assertContains($roleTA->id, $tmu->roles()->pluck('id')->toArray());
        $this->assertContains($roleTutor->id, $tmu->roles()->pluck('id')->toArray());
    }

    public function testUpdateRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleUser $tmuOther */
        $tmuOther = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);
        $tmuOther->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);

        $roleTA = Role::where('name', TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE)->firstOrFail();
        $roleTutor = Role::where('name', TeachingModuleUserPolicy::TUTOR_ROLE)->firstOrFail();

        $request = function() use ($tmu, $tmuOther, $roleTA, $roleTutor) {
            /** @var User $user */
            $user = $tmu->user()->first();

            return $this->actingAs($user)->put(route('modules.users.update', [
                'module' => $tmu->teaching_module_id,
                'user' => $tmuOther->id
            ]), [
                'roles' => [$roleTA->id, $roleTutor->id]
            ]);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request);
    }

    public function testDestroyForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $module->id
        ]);

        $url = route('modules.users.destroy', ['module' => $module->id, 'user' => $tmu->id]);
        $response = $this->actingAs($user)->delete($url);
        $response->assertForbidden();
        TeachingModuleUser::findOrFail($tmu->id);
    }

    public function testDestroyDirectPermission() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::REMOVE_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $module->id
        ]);

        $url = route('modules.users.destroy', ['module' => $module->id, 'user' => $tmu->id]);
        $response = $this->actingAs($user)->delete($url);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDeleted($tmu);
    }

    public function testDestroyRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleUser $tmuOther */
        $tmuOther = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $request = function() use ($tmu, $tmuOther) {
            /** @var User $user */
            $user = $tmu->user()->first();
            $url = route('modules.users.destroy', ['module' => $tmu->teaching_module_id, 'user' => $tmuOther->id]);
            return $this->actingAs($user)->delete($url);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request);
    }

    public function testDestroyManyForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $module->id
        ]);

        $response = $this->actingAs($user)->delete(route('modules.users.destroyMany', $module->id), [
            'ids' => [$tmu->id]
        ]);
        $response->assertForbidden();
        TeachingModuleUser::findOrFail($tmu->id);
    }

    public function testDestroyManyDirectPermission() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::REMOVE_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->count(3)->create([
            'teaching_module_id' => $module->id
        ]);

        $response = $this->actingAs($user)->delete(route('modules.users.destroyMany', $module->id), [
            'ids' => [$tmu[0]->id, $tmu[1]->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDeleted($tmu[0]);
        $this->assertDeleted($tmu[1]);

        $this->assertEquals(1, TeachingModuleUser::count());
        $this->assertEquals($tmu[2]->id, TeachingModuleUser::first()->id);
    }

    public function testDestroyManyDirectPermissionDifferentModule() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::REMOVE_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $modules = TeachingModule::factory()->count(2)->create();
        /** @var TeachingModuleUser $tmu */
        $tmuA = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $modules[0]->id
        ]);
        /** @var TeachingModuleUser $tmu */
        $tmuB = TeachingModuleUser::factory()->create([
            'teaching_module_id' => $modules[1]->id
        ]);

        // We try to delete one user from the right module, and one user from the right module:
        // the validation should prevent either from being deleted.
        $response = $this->actingAs($user)->delete(route('modules.users.destroyMany', $modules[1]->id), [
            'ids' => [$tmuA->id, $tmuB->id]
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('ids.0');
        $this->assertEquals(2, TeachingModuleUser::count());
    }

    public function testDestroyManyRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleUser $tmuOthers */
        $tmuOthers = TeachingModuleUser::factory()->count(2)->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $request = function() use ($tmu, $tmuOthers) {
            /** @var User $user */
            $user = $tmu->user()->first();
            return $this->actingAs($user)->delete(route('modules.users.destroyMany', $tmu->teaching_module_id), [
                'ids' => [$tmuOthers[0]->id, $tmuOthers[1]->id]
            ]);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request);
    }

    public function testAutocompleteForbidden()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $url = route('modules.users.autocomplete', ['module' => $module->id, 'prefix' => 'abc']);
        $response = $this->actingAs($user)->get($url);
        $response->assertForbidden();
    }

    public function testAutocompleteMissingPrefix()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::ENROL_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(
            route('modules.users.autocomplete', $module->id));
        $response->assertStatus(400);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson(['error' => 'Prefix is missing']);
    }

    public function testAutocompleteWithPrefix()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleUserPolicy::ENROL_USERS_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $uA1 = User::factory()->create(['email' => 'a12@example.com']);
        $uA2 = User::factory()->create(['email' => 'a48@example.com']);
        User::factory()->create(['email' => 'b232@example.com']);

        $response = $this->actingAs($user)->get(
            route('modules.users.autocomplete', ['module' => $module->id, 'prefix' => 'a']));
        $response->assertSuccessful();
        $response->assertJson([$uA1->email, $uA2->email]);

        // Test that % is dropped

        $response = $this->actingAs($user)->get(
            route('modules.users.autocomplete', ['module' => $module->id, 'prefix' => '%23']));
        $response->assertSuccessful();
        $response->assertJson([]);

        // Test that _ is dropped

        $response = $this->actingAs($user)->get(
            route('modules.users.autocomplete', ['module' => $module->id, 'prefix' => '_48']));
        $response->assertSuccessful();
        $response->assertJson([]);
    }

    public function testAutocompleteRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        User::factory()->count(5)->create();

        $request = function() use ($tmu) {
            /** @var User $user */
            $user = $tmu->user()->first();
            return $this->actingAs($user)->get(
                route('modules.users.autocomplete', [
                    'module' => $tmu->teaching_module_id,
                    'prefix' => 'a']));;
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
        ], [
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testImportForbidden() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $url = route('modules.users.import', $tmu->teaching_module_id);
        $response = $this->actingAs($tmu->user)->get($url);
        $response->assertForbidden();
    }

    public function testImportAdmin() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->assignRole(User::SUPER_ADMIN_ROLE);
        User::setAdminMode(true);

        $url = route('modules.users.import', $tmu->teaching_module_id);
        $response = $this->actingAs($tmu->user)->get($url);
        $response->assertSuccessful();
        $response->assertViewIs('modules.users.import');
        $response->assertViewHas('module');
    }

    public function testImportRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $url = route('modules.users.import', $tmu->teaching_module_id);

        $request = function () use ($tmu, $url) {
            return $this->actingAs($tmu->user)->get($url);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
        ], [
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, 200);
    }

    public function testImportMissingFile() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        //'jobfile' => UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath))

        // missing the csvfile
        $response = $this->actingAs($tmu->user)->post(route('modules.users.import', $tmu->teaching_module_id));
        $response->assertRedirect();
        $response->assertSessionHasErrors('csvfile');
    }

    public function testImportNotFile() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        //'jobfile' => UploadedFile::fake()->createWithContent('java-policy.zip', file_get_contents($zipPath))

        // missing the csvfile
        $response = $this->actingAs($tmu->user)->post(route('modules.users.import', $tmu->teaching_module_id), [
            'csvfile' => 23
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('csvfile');
    }

    public function testImportNotCSVFile() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        // missing the CSV file
        $response = $this->actingAs($tmu->user)->post(route('modules.users.import', $tmu->teaching_module_id), [
            'csvfile' => UploadedFile::fake()->createWithContent('pom.xml',
                file_get_contents('test-resources/java-policy/pom.xml'))
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('csvfile');
    }

    public function testImportBadCSVFile() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        // CSV file is missing the "Username" column
        $response = $this->actingAs($tmu->user)->post(route('modules.users.import', $tmu->teaching_module_id), [
            'csvfile' => UploadedFile::fake()->createWithContent('studinfo.csv',
                file_get_contents('test-resources/sample-studinfo-bad.csv'))
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('csvfile');
    }

    public function testImportGoodCSVFile() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        // CSV file is missing the "Username" column
        $response = $this->actingAs($tmu->user)->post(route('modules.users.import', $tmu->teaching_module_id), [
            'csvfile' => UploadedFile::fake()->createWithContent('studinfo.csv',
                file_get_contents('test-resources/sample-studinfo-bb.csv'))
        ]);
        $response->assertRedirect(route('modules.users.index', $tmu->teaching_module_id));
        $response->assertSessionHasNoErrors();

        // Two users should have been imported into the module
        $this->assertEquals(3, $tmu->module->users()->count());

        // It should be possible for ldapuser2 to log in now
        Auth::logout();
        $response = $this->post(route('login'), [
            'email' => DevelopmentUserSeeder::LDAP_MISSING_EMAIL,
            'password' => DevelopmentUserSeeder::LDAP_MISSING_PASSWORD
        ]);
        $response->assertRedirect(route('home'));
        $response->assertSessionHasNoErrors();

        /** @var User $user */
        $user = User::where('email', DevelopmentUserSeeder::LDAP_MISSING_EMAIL)->first();
        $this->assertNotNull($user->guid);
        $this->assertNotNull($user->domain);
    }

}
