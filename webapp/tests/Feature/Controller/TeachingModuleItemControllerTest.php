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
use App\Folder;
use App\Http\Controllers\TeachingModuleItemController;
use App\Policies\TeachingModuleItemPolicy;
use App\Policies\TeachingModulePolicy;
use App\Policies\TeachingModuleUserPolicy;
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\User;
use Carbon\Carbon;
use Tests\Feature\TMURoleSwitchingTestCase;

class TeachingModuleItemControllerTest extends TMURoleSwitchingTestCase
{
    public function testMarkdownHighlighting() {
        $this->setupItemEdition($user, $item);
        /**
         * @var User $user
         * @var TeachingModuleItem $item
         */
        $user->givePermissionTo(TeachingModulePolicy::VIEW_PERMISSION);
        $user->givePermissionTo(TeachingModuleItemPolicy::VIEW_AVAILABLE_PERMISSION);
        $item->available = true;
        $item->description_markdown = "```java\nint x = 2;\n```";
        $item->save();

        $response = $this->actingAs($user)->get(
            route('modules.show', $item->teaching_module_id));
        $response->assertSuccessful();
        $response->assertSee('hljs');
    }

    public function testCreateItemForbidden() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $response = $this->actingAs($tmu->user)->get(route('modules.items.create', $tmu->teaching_module_id));
        $response->assertForbidden();
    }

    public function testCreateItemDirectPermission() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        $response = $this->actingAs($tmu->user)->get(route('modules.items.create', $tmu->teaching_module_id));
        $response->assertSuccessful();
        $response->assertViewIs('modules.items.create');
        $response->assertViewHas('module');
        $response->assertViewHas('item');
    }

    public function testCreateItemFolder() {
        $this->assertCreateItemWithTypeWorks('folder');
    }

    public function testCreateItemAssessment() {
        $this->assertCreateItemWithTypeWorks('assessment');
    }

    public function testCreateItemInsideFolder() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->usage()->save($item);

        $url = route('modules.items.create', [
            'module' => $tmu->teaching_module_id, 'folder' => $folder->id
        ]);
        $response = $this->actingAs($tmu->user)->get($url);
        $response->assertSuccessful();
        $response->assertSee('itemFolder');
    }

    public function testCreateItemRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        $request = function () use ($tmu) {
            return $this->actingAs($tmu->user)->get(route('modules.items.create', $tmu->teaching_module_id));
        };

        $this->assertOnlyTutorsCanManageItems($tmu, $request, 200);
    }

    public function testStoreForbidden() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), []);
        $response->assertForbidden();
    }

    public function testStoreDirectPermission() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemAvailableStart' => $item->available_from->format(TeachingModuleItemController::DATETIME_FORMAT),
            'itemAvailableEnd' => $item->available_until->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, TeachingModuleItem::count());
    }

    public function testStoreDirectPermissionWithFolderContent() {
        $this->assertStoreItemWithTypeWorks('folder', 'App\Folder');
    }

    public function testStoreDirectPermissionWithAssessmentContent() {
        $this->assertStoreItemWithTypeWorks('assessment', 'App\Assessment');
    }

    public function testStoreDirectPermissionInsideFolder() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);
        /** @var TeachingModuleItem $containerItem */
        $containerItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->usage()->save($containerItem);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();
        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemFolder' => $folder->id,
        ]);
        $item = TeachingModuleItem::where('title', $item->title)->firstOrFail();

        $response->assertRedirect(route('modules.items.show', [
            'module' => $item->teaching_module_id,
            'item' => $containerItem->id,
        ]));
        $response->assertSessionHasNoErrors();

        $this->assertEquals($folder->id, $item->folder->id);
        $this->assertContains($item->id, $folder->children()->pluck('id')->toArray());
    }

    public function testStoreDirectPermissionInsideAnotherModuleFolder() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleUser $tmu2 */
        $tmu2 = TeachingModuleUser::factory()->create([
            'user_id' => $tmu->user_id
        ]);
        $tmu2->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $containerItem */
        $containerItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id,
        ]);
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->usage()->save($containerItem);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();
        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu2->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            // PROBLEM: $folder is inside $tmu->module, not inside $tmu2->module!
            'itemFolder' => $folder->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('itemFolder');

        $this->assertEquals(1, TeachingModuleItem::count());
    }

    public function testStoreDirectPermissionOnlyStart() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemAvailableStart' => $item->available_from->format(TeachingModuleItemController::DATETIME_FORMAT),
            'itemAvailableEnd' => null,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, TeachingModuleItem::count());
    }

    public function testStoreDirectPermissionOnlyEnd() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemAvailableStart' => null,
            'itemAvailableEnd' => $item->available_until->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, TeachingModuleItem::count());
    }

    public function testStoreRequiredFields() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), []);
        $response->assertRedirect();
        $response->assertSessionHasErrors(['itemTitle', 'itemDescription']);
        $response->assertSessionDoesntHaveErrors(['itemAvailable', 'itemAvailableStart', 'itemAvailableEnd']);
        $this->assertEquals(0, TeachingModuleItem::count());
    }

    public function testStoreDatesBadFormat() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemAvailableStart' => 'abc',
            'itemAvailableEnd' => 'abc',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors(['itemAvailableStart', 'itemAvailableEnd']);
        $response->assertSessionDoesntHaveErrors(['itemTitle', 'itemDescription', 'itemAvailable']);
        $this->assertEquals(0, TeachingModuleItem::count());
    }

    public function testStoreDatesBadRange() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemAvailableStart' => Carbon::now()->add(1, 'minute')->format(TeachingModuleItemController::DATETIME_FORMAT),
            'itemAvailableEnd' => Carbon::now()->add(-1, 'minute')->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors(['itemAvailableStart', 'itemAvailableEnd']);
        $response->assertSessionDoesntHaveErrors(['itemTitle', 'itemDescription', 'itemAvailable']);
        $this->assertEquals(0, TeachingModuleItem::count());
    }

    public function testStoreDueBy() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemType' => 'assessment',
            'itemAvailableStart' => Carbon::now()->add(-1, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
            'itemAvailableEnd' => Carbon::now()->add(1, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
            'dueBy' => Carbon::now()->add(-10, 'minute')->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, TeachingModuleItem::count());
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::all()->first();
        $this->assertNotNull($tmi->content->due_by);
    }

    public function testStoreDueByBeforeStart() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemType' => 'assessment',
            'itemAvailableStart' => Carbon::now()->add(-1, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
            'dueBy' => Carbon::now()->add(-2, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertEquals(0, TeachingModuleItem::count());
    }

    public function testStoreDueByAfterEnd() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemType' => 'assessment',
            'itemAvailableEnd' => Carbon::now()->add(1, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
            'dueBy' => Carbon::now()->add(2, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertEquals(0, TeachingModuleItem::count());
    }

    public function testStoreRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $request = function () use ($tmu, $item) {
            return $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
                'itemTitle' => $item->title,
                'itemDescription' => $item->description_markdown,
                'itemAvailable' => $item->available,
                'itemAvailableStart' => $item->available_from->format(TeachingModuleItemController::DATETIME_FORMAT),
                'itemAvailableEnd' => $item->available_until->format(TeachingModuleItemController::DATETIME_FORMAT),
            ]);
        };

        $this->assertOnlyTutorsCanManageItems($tmu, $request);
    }

    public function testEditItemForbidden() {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();

        $response = $this->actingAs($user)->get(route('modules.items.edit', [
            'module' => $item->teaching_module_id,
            'item' => $item->id
        ]));
        $response->assertForbidden();
    }

    public function testEditItemDirectPermission()
    {
        $this->setupItemEdition($user, $item);

        $response = $this->actingAs($user)->get(route('modules.items.edit', [
            'module' => $item->teaching_module_id,
            'item' => $item->id
        ]));
        $response->assertSuccessful();
        $response->assertViewIs('modules.items.create');
        $response->assertViewHas('module');
        $response->assertViewHas('item');
    }

    public function testEditItemRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $request = function () use ($tmu, $item) {
            return $this->actingAs($tmu->user)->get(route('modules.items.edit', [
                'module' => $item->teaching_module_id,
                'item' => $item->id
            ]));
        };

        $this->assertOnlyTutorsCanManageItems($tmu, $request, 200);
    }

    public function testUpdateForbidden() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $response = $this->actingAs($tmu->user)->put($this->updateRoute($item), []);
        $response->assertForbidden();
    }

    public function testUpdateUnsetDueBy() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleItemPolicy::UPDATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();

        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create([
            'due_by' => Carbon::now()
        ]);
        $assessment->usage()->save($item);
        $item = $assessment->usage;

        $response = $this->actingAs($user)->put($this->updateRoute($item), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'itemAvailableStart' => $item->available_from->format(TeachingModuleItemController::DATETIME_FORMAT),
            'itemAvailableEnd' => $item->available_until->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);

        $assessment->refresh();
        $this->assertNull($assessment->due_by);
    }

    public function testUpdateSetDueBy() {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleItemPolicy::UPDATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();

        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        $assessment->usage()->save($item);
        $this->assertNull($assessment->due_by);
        $item = $assessment->usage;

        $response = $this->actingAs($user)->put($this->updateRoute($item), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => $item->available,
            'dueBy' => Carbon::now()->add(1, 'hour')->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);

        $assessment->refresh();
        $this->assertNotNull($assessment->due_by);
    }

    public function testUpdateMakeUnavailable() {
        $this->setupItemEdition($user, $item);
        /** @var TeachingModuleItem $item */
        $item->available = true;
        $item->save();

        $response = $this->actingAs($user)->put($this->updateRoute($item), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemAvailable' => false,
            'itemAvailableStart' => $item->available_from->format(TeachingModuleItemController::DATETIME_FORMAT),
            'itemAvailableEnd' => $item->available_until->format(TeachingModuleItemController::DATETIME_FORMAT),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertFalse($item->available);
    }

    public function testUpdateRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $request = function () use ($tmu, $item) {
            return $this->actingAs($tmu->user)->put($this->updateRoute($item), [
                'itemTitle' => $item->title,
                'itemDescription' => $item->description_markdown,
                'itemAvailable' => false,
                'itemAvailableStart' => $item->available_from->format(TeachingModuleItemController::DATETIME_FORMAT),
                'itemAvailableEnd' => $item->available_until->format(TeachingModuleItemController::DATETIME_FORMAT),
            ]);
        };

        $this->assertOnlyTutorsCanManageItems($tmu, $request);
    }

    public function testDestroyForbidden() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $response = $this->actingAs($tmu->user)->delete(route('modules.items.destroy', [
            'module' => $tmu->teaching_module_id,
            'item' => $item->id,
        ]));
        $response->assertForbidden();
    }

    public function testDestroyTutorRole() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $tmu->assignRole(TeachingModuleUserPolicy::TUTOR_ROLE);
        $response = $this->actingAs($tmu->user)->delete(route('modules.items.destroy', [
            'module' => $tmu->teaching_module_id,
            'item' => $item->id,
        ]));
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDeleted($item);
    }

    public function testDestroyRoles() {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $tmu->teaching_module_id
        ]);

        $request = function () use ($tmu, $item) {
            return $this->actingAs($tmu->user)->delete(route('modules.items.destroy', [
                'module' => $tmu->teaching_module_id,
                'item' => $item->id,
            ]));
        };

        $this->assertOnlyTutorsCanManageItems($tmu, $request);
    }

    /**
     * @param User $user
     * @param TeachingModuleItem $item
     */
    private function setupItemEdition(&$user, &$item): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->givePermissionTo(TeachingModuleItemPolicy::UPDATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();
    }

    /**
     * @param TeachingModuleItem $item
     * @return string
     */
    private function updateRoute(TeachingModuleItem $item): string
    {
        return route('modules.items.update', [
            'module' => $item->teaching_module_id,
            'item' => $item->id
        ]);
    }

    /**
     * @param TeachingModuleUser $tmu
     * @param \Closure $request
     * @param int $status
     */
    private function assertOnlyTutorsCanManageItems(TeachingModuleUser $tmu, \Closure $request, int $status = 302): void
    {
        $this->assertRolesWork($tmu, [
            TeachingModuleUserPolicy::STUDENT_ROLE,
            TeachingModuleUserPolicy::OBSERVER_ROLE,
            TeachingModuleUserPolicy::TEACHING_ASSISTANT_ROLE,
        ], [
            TeachingModuleUserPolicy::TUTOR_ROLE,
        ], $request, $status);
    }

    /**
     * @param string $itemType
     */
    private function assertCreateItemWithTypeWorks(string $itemType): void
    {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->user->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        $url = route('modules.items.create', [
            'module' => $tmu->teaching_module_id, 'type' => $itemType
        ]);
        $response = $this->actingAs($tmu->user)->get($url);
        $response->assertSuccessful();
        $response->assertSee('itemType');
    }

    /**
     * @param string $itemType
     * @param string $itemClass
     */
    private function assertStoreItemWithTypeWorks(string $itemType, string $itemClass): void
    {
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::factory()->create();
        $tmu->givePermissionTo(TeachingModuleItemPolicy::CREATE_PERMISSION);

        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->make();

        $response = $this->actingAs($tmu->user)->post(route('modules.items.store', $tmu->teaching_module_id), [
            'itemTitle' => $item->title,
            'itemDescription' => $item->description_markdown,
            'itemType' => $itemType,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, TeachingModuleItem::count());
        $this->assertEquals($itemClass, TeachingModuleItem::first()->content_type);
    }
}
