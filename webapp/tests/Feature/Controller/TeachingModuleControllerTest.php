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

use App\Folder;
use App\Policies\TeachingModulePolicy;
use App\Policies\TeachingModuleUserPolicy;
use App\TeachingModule;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\User;
use Tests\Feature\TMURoleSwitchingTestCase;

class TeachingModuleControllerTest extends TMURoleSwitchingTestCase
{
    public function testIndexNoModulesAdmin()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::VIEW_PERMISSION);

        $response = $this->actingAs($user)->get(route('modules.index'));
        $response->assertSuccessful();
        $response->assertViewIs('modules.index');
        $response->assertViewHas('modules');
    }

    public function testIndexSomeModulesAdmin() {
        TeachingModule::factory()->count(3)->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::VIEW_PERMISSION);

        $response = $this->actingAs($user)->get(route('modules.index'));
        $response->assertSuccessful();
        $response->assertViewIs('modules.index');
        $response->assertViewHas('modules');
    }

    public function testIndexSomeModulesUser() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        TeachingModule::factory()->count(3)->create();

        $response = $this->actingAs($tmu->user()->first())->get(route('modules.index'));
        $response->assertSuccessful();
        $response->assertViewIs('modules.index');
        $response->assertViewHas('modules');

        $shownModules = $response->viewData('modules');
        $this->assertEquals(1, $shownModules->count());
        $this->assertEquals($tmu->teaching_module_id, $shownModules->first()->id);
    }

    public function testViewForbidden() {
        $module = TeachingModule::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.show', $module->id));
        $response->assertForbidden();
    }

    public function testViewDirectPermission() {
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::VIEW_PERMISSION);
        $this->createSampleItems($module, $itemAvailable, $itemUnavailable);

        $response = $this->actingAs($user)->get(route('modules.show', $module->id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.show');
        $response->assertViewHas('module');
        $response->assertViewHas('items');

        // Without the VIEW_AVAILABLE_PERMISSION, you cannot see *any* items
        $response->assertDontSee($itemAvailable->title);
        $response->assertDontSee($itemUnavailable->title);
    }

    public function testViewStudentRole() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        // Students can see available items
        $tmu->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);

        $this->createSampleItems($tmu->module, $itemAvailable, $itemUnavailable);
        /** @var TeachingModuleItem $itemAvailable */
        /** @var TeachingModuleItem $itemUnavailable */

        $response = $this->actingAs($tmu->user()->first())
            ->get(route('modules.show', $tmu->teaching_module_id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.show');
        $response->assertViewHas('module');
        $response->assertViewHas('items');
        $response->assertSee($itemAvailable->title);
        $response->assertDontSee($itemUnavailable->title);
    }

    public function testViewTeachingAssistantRole() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        // TAs and tutors can see unavailable items
        $tmu->assignRole(TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE);

        $this->createSampleItems($tmu->module, $itemAvailable, $itemUnavailable);
        /** @var TeachingModuleItem $itemAvailable */
        /** @var TeachingModuleItem $itemUnavailable */

        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->usage()->save($itemAvailable);
        $availableInSubfolder = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id,
            'folder_id' => $folder->id,
            'title' => 'AvailableInHiddenFolder',
            'available' => true,
            'available_from' => null,
            'available_until' => null,
        ]);

        $response = $this->actingAs($tmu->user()->first())
            ->get(route('modules.show', $tmu->teaching_module_id));
        $response->assertSuccessful();
        $response->assertSee($itemAvailable->title);
        $response->assertSee($itemUnavailable->title);
        $response->assertDontSee($availableInSubfolder->title);
    }

    public function testCreateForbidden() {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('modules.create'));
        $response->assertForbidden();
    }

    public function testCreateAuthorized() {
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::CREATE_PERMISSION);

        $response = $this->actingAs($user)->get(route('modules.create'));
        $response->assertSuccessful();
        $response->assertViewIs('modules.create');
    }

    public function testStoreForbidden() {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('modules.store'), ['name' => 'abc']);
        $response->assertForbidden();
        $this->assertEquals(0, TeachingModule::count());
    }

    public function testStoreMissingName() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::CREATE_PERMISSION);

        $response = $this->actingAs($user)->post(route('modules.store'), ['name' => '']);
        $response->assertRedirect();
        $response->assertSessionHasErrors('name');
        $this->assertEquals(0, TeachingModule::count());
    }

    public function testStoreNameIncluded() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::CREATE_PERMISSION);

        $response = $this->actingAs($user)->post(route('modules.store'), ['name' => 'abc']);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, TeachingModule::count());

        $module = TeachingModule::first();
        $this->assertEquals('abc', $module->name);
    }

    public function testEditForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.edit', $module->id));
        $response->assertForbidden();
    }

    public function testEditAdmin() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::UPDATE_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.edit', $module->id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.create');
        $response->assertViewHas('module');
    }

    public function testEditStudentForbidden() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);

        $response = $this->actingAs($tmu->user()->first())
            ->get(route('modules.edit', $tmu->teaching_module_id));
        $response->assertForbidden();
    }

    public function testEditTutorAuthorized() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);

        // Test with just one role, to check that the default role picking logic works
        $response = $this->actingAs($tmu->user()->first())
            ->get(route('modules.edit', $tmu->teaching_module_id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.create');
        $response->assertViewHas('module');
    }

    public function testEditRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var User $user */
        $user = $tmu->user()->first();

        // Try switching between roles and testing forbidden / authorized
        $request = function () use ($user, $tmu) {
            return $this->actingAs($user)
                ->get(route('modules.edit', $tmu->teaching_module_id));;
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE],
            [TeachingModuleUserPolicy::TUTOR_ROLE], $request, 200);
    }

    public function testUpdateForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        $oldName = $module->name;

        $response = $this->actingAs($user)->get(route('modules.update', $module->id), [
            'name' => 'abc'
        ]);
        $response->assertForbidden();
        $module->refresh();
        $this->assertEquals($oldName, $module->name);
    }

    public function testUpdateInvalid() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::UPDATE_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        $oldName = $module->name;

        $response = $this->actingAs($user)->put(route('modules.update', $module->id), [
            'name' => ''
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('name');

        $module->refresh();
        $this->assertEquals($oldName, $module->name);
    }

    public function testUpdateValid() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::UPDATE_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->put(route('modules.update', $module->id), [
            'name' => 'newName'
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $module->refresh();
        $this->assertEquals('newName', $module->name);
    }

    public function testUpdateRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var User $user */
        $user = $tmu->user()->first();

        $request = function () use ($user, $tmu) {
            return $this->actingAs($user)->put(route('modules.update', $tmu->teaching_module_id), [
                'name' => 'newName'
            ]);
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE
        ], $request);
    }

    public function testDeleteForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->delete(route('modules.destroy', $module->id));
        $response->assertForbidden();

        TeachingModule::findOrFail($module->id);
    }

    public function testDeleteAdmin() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModulePolicy::DELETE_PERMISSION);
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $response = $this->actingAs($user)->delete(route('modules.destroy', $module->id));
        $response->assertRedirect();
        $this->assertDeleted($module);
    }

    public function testDeleteRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var User $user */
        $user = $tmu->user()->first();

        $request = function () use ($user, $tmu) {
            return $this->actingAs($user)->delete(route('modules.destroy', $tmu->teaching_module_id));
        };

        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
            TeachingModuleUserPolicy::TUTOR_ROLE
        ], [], $request);

        TeachingModuleUser::findOrFail($tmu->id);
    }

    /**
     * @param TeachingModule $module
     * @param $itemAvailable
     * @param $itemUnavailable
     */
    private function createSampleItems(TeachingModule $module, &$itemAvailable, &$itemUnavailable): void
    {
        /** @var TeachingModuleItem $itemAvailable */
        $itemAvailable = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'title' => 'AvailableItem',
            'available' => true,
        ]);
        /** @var TeachingModuleItem $itemUnavailable */
        $itemUnavailable = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'title' => 'UnavailableItem',
            'available' => false,
        ]);
    }
}
