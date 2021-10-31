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

namespace Tests\Feature\Model;

use App\Folder;
use App\TeachingModule;
use App\TeachingModuleItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingModuleItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function testCannotSaveJustContentType() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->make();
        $tmi->content_type = 'App\Folder';
        $this->assertFalse($tmi->save());
        $this->assertNull($tmi->id);
    }

    public function testCannotSaveJustContentID() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->make();
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $tmi->content_id = $folder->id;
        $this->assertFalse($tmi->save());
        $this->assertNull($tmi->id);
    }

    public function testCannotSaveInvalidContentID() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->make();
        $tmi->content_type ='App\Folder';
        $tmi->content_id = 1;
        $this->assertFalse($tmi->save());
        $this->assertNull($tmi->id);
    }

    public function testCannotSaveInvalidContentType() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->make();
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $tmi->content_type ='App\FolderFake';
        $tmi->content_id = $folder->id;

        // Eloquent will complain with 'Class not found'
        $this->expectException(\Error::class);
        $tmi->save();
    }

    public function testCanSaveWithNoInterval() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create([
            'available_from' => null,
            'available_until' => null,
        ]);
        $this->assertNotNull($tmi->id);
    }

    public function testCanSaveWithOnlyStart() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create([
            'available_from' => Carbon::now(),
            'available_until' => null,
        ]);
        $this->assertNotNull($tmi->id);
    }

    public function testCanSaveWithOnlyEnd() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create([
            'available_from' => null,
            'available_until' => Carbon::now(),
        ]);
        $this->assertNotNull($tmi->id);
    }

    public function testCannotSaveInvalidInterval() {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->make([
            'available_from' => Carbon::now()->add(1, 'day'),
            'available_until' => Carbon::now()->add(-1, 'day'),
        ]);
        $this->assertFalse($tmi->save());
    }

    public function testAvailable() {
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'available' => true,
            'available_from' => null,
            'available_until' => null,
        ]);
        $this->assertTrue($item->isAvailable());
        $this->assertTrue($item->isDirectlyAvailable());

        $item->available = false;
        $this->assertFalse($item->isAvailable());
        $this->assertFalse($item->isDirectlyAvailable());

        $item->available = true;
        $item->available_from = Carbon::now();
        $this->assertTrue($item->isAvailable());
        $this->assertTrue($item->isDirectlyAvailable());
        $item->available_from = Carbon::now()->add(1, 'day');
        $this->assertFalse($item->isAvailable());
        $this->assertFalse($item->isDirectlyAvailable());
        $item->available_from = null;

        $item->available_until = Carbon::now()->add(10, 'minutes');
        $this->assertTrue($item->isAvailable());
        $this->assertTrue($item->isDirectlyAvailable());
        $item->available_until = Carbon::now()->add(-10, 'minutes');
        $this->assertFalse($item->isAvailable());
        $this->assertFalse($item->isDirectlyAvailable());
        $item->available_until = null;

        // Good range: [-1 day, +1 day]
        $item->available_from = Carbon::now()->add(-1, 'day');
        $item->available_until = Carbon::now()->add(1, 'day');
        $this->assertTrue($item->isAvailable());
        $this->assertTrue($item->isDirectlyAvailable());

        // Bad (empty) flipped range: [+1 day, -11 day]
        $item->available_from = Carbon::now()->add(1, 'day');
        $item->available_until = Carbon::now()->add(-1, 'day');
        $this->assertFalse($item->isAvailable());
        $this->assertFalse($item->isDirectlyAvailable());
    }

    public function testChildrenStore()
    {
        $this->setupChildren($folder, $child);
        $this->assertEquals(3, $folder->children->count());
        $this->assertEquals($folder->id, $child->folder->id);
    }

    public function testAvailableChildren() {
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->usage()->save($item);

        $this->createChildrenItems($item->module, $folder->id, $expectedAvailable, $expectedUnavailable);
        $availableItems = $folder->availableChildren()->pluck('id')->toArray();

        foreach ($expectedAvailable as $av) {
            $this->assertContains($av->id, $availableItems);
        }
        foreach ($expectedUnavailable as $unav) {
            $this->assertNotContains($unav->id, $availableItems);
        }
    }

    public function testChildrenDeleteChild()
    {
        $this->setupChildren($folder, $child);

        // Deleting a child should remove it from the folder's children
        $child->delete();
        $this->assertDeleted($child);
        $folder->refresh();
        $this->assertEquals(2, $folder->children->count());
    }

    public function testChildrenDeleteFolder()
    {
        $this->setupChildren($folder, $child);

        // Deleting the folder should delete all children as well
        $folder->delete();
        $this->assertDeleted($folder);
        $this->assertDeleted($child);
    }

    public function testUsages()
    {
        $this->setupUsages($tmi, $folder);
        $this->assertNotNull($tmi->module->id);
        $this->assertEquals($folder->id, $tmi->content->id);
        $this->assertNotNull($folder->usage);
        $this->assertEquals($tmi->id, $folder->usage->id);
    }

    public function testUsagesDeleteFolderClearsUse() {
        $this->setupUsages($tmi, $folder);
        $folder->delete();
        $tmi->refresh();
        $this->assertNull($tmi->content);
    }

    public function testUsagesDeleteItemReflectedOnFolder() {
        $this->setupUsages($tmi, $folder);
        $tmi->delete();
        $this->assertNull($folder->usage);
        $this->assertDeleted($folder);
    }

    public function testRootModuleItems() {
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();
        /** @var Folder $folder */
        $folder = Folder::factory()->create();

        // Root of the module has folder + 2 other items
        /** @var TeachingModuleItem $folderItem */
        $folderItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id
        ]);
        $folder->usage()->save($folderItem);
        $module->items()->saveMany(
            TeachingModuleItem::factory()->count(2)->make([
                'teaching_module_id' => $module->id
            ])
        );
        $this->assertEquals($folder->id, $folderItem->content->id);

        // Then the folder item has 2 children
        $folder->children()->saveMany(TeachingModuleItem::factory()->count(2)->make([
            'teaching_module_id' => $module->id
        ]));

        // The queries separate the direct children from the children of the folder
        $this->assertEquals(3, $module->children->count());
        $this->assertEquals(5, $module->items->count());

        // Deleting the module should delete all its items (with their content)
        $module->delete();
        $this->assertEquals(0, TeachingModule::count());
        $this->assertEquals(0, TeachingModuleItem::count());
        $this->assertEquals(0, Folder::count());
    }

    public function testAvailableRootModuleItems() {
        /** @var TeachingModule $module */
        $module = TeachingModule::factory()->create();

        $this->createChildrenItems($module, null, $expectedAvailable, $expectedUnavailable);
        $availableItems = $module->availableChildren()->pluck('id')->toArray();

        foreach ($expectedAvailable as $av) {
            $this->assertContains($av->id, $availableItems);
        }
        foreach ($expectedUnavailable as $unav) {
            $this->assertNotContains($unav->id, $availableItems);
        }
    }

    public function testPathSingle() {
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create();
        $this->assertEquals([$item], $item->path());
    }

    public function testPathMultiple() {
        /** @var TeachingModuleItem $item */
        $item = TeachingModuleItem::factory()->create([
            'available' => true,
        ]);

        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->usage()->save($item);

        /** @var TeachingModuleItem $itemChild */
        $itemChild = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $item->teaching_module_id,
            'available' => true,
        ]);
        $folder->children()->save($itemChild);

        $this->assertEquals([$item], $item->path());

        // Paths go from the root to the item itself
        $childPath = $itemChild->path();
        $this->assertCount(2, $childPath);
        $this->assertEquals($item->id, $childPath[0]->id);
        $this->assertEquals($itemChild->id, $childPath[1]->id);
        $this->assertTrue($item->isAvailable());
        $this->assertTrue($itemChild->isAvailable());

        // If the parent becomes unavailable, the child becomes unavailable as well
        $item->available = false;
        $item->save();
        $itemChild->refresh();
        $this->assertFalse($item->isAvailable());
        $this->assertFalse($item->isDirectlyAvailable());
        $this->assertFalse($itemChild->isAvailable());

        // isDirectlyAvailable does not consider parent folders:
        // it assumes you have already checked the container is accessible
        $this->assertTrue($itemChild->isDirectlyAvailable());
    }

    private function setupChildren(&$folder, &$firstChild) {
        /** @var Folder $folder */
        $folder = Folder::factory()->create();
        $folder->children()->saveMany(TeachingModuleItem::factory()->count(3)->create());
        $folder->usage()->save(TeachingModuleItem::factory()->create());

        /** @var TeachingModuleItem $firstChild */
        $firstChild = $folder->children->first();
    }

    private function setupUsages(&$tmi, &$folder) {
        /** @var TeachingModuleItem $tmi */
        $tmi = TeachingModuleItem::factory()->create();
        /** @var Folder $folder */
        $folder = Folder::factory()->create();

        $folder->usage()->save($tmi);
    }

    /**
     * @param TeachingModule $module
     * @param $folder_id
     * @param $expectedAvailable
     * @param $expectedUnavailable
     */
    private function createChildrenItems(TeachingModule $module, $folder_id, &$expectedAvailable, &$expectedUnavailable): void
    {
        $availableItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => null,
            'available_until' => null,
        ]);
        $unavailableItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => false,
            'available_from' => null,
            'available_until' => null,
        ]);
        $tooEarlyItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => Carbon::now()->add(10, 'minutes'),
            'available_until' => null,
        ]);
        $tooLateItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => null,
            'available_until' => Carbon::now()->add(-10, 'minutes'),
        ]);
        $goodStartItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => Carbon::now()->add(-1, 'day'),
            'available_until' => null,
        ]);
        $goodEndItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => null,
            'available_until' => Carbon::now()->add(1, 'day'),
        ]);
        $goodRangeItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => Carbon::now()->add(-1, 'day'),
            'available_until' => Carbon::now()->add(1, 'day'),
        ]);
        $tooLateRangeItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => Carbon::now()->add(-5, 'day'),
            'available_until' => Carbon::now()->add(-1, 'day'),
        ]);
        $tooEarlyRangeItem = TeachingModuleItem::factory()->create([
            'teaching_module_id' => $module->id,
            'folder_id' => $folder_id,
            'available' => true,
            'available_from' => Carbon::now()->add(1, 'day'),
            'available_until' => Carbon::now()->add(5, 'day'),
        ]);

        $expectedAvailable = [$availableItem, $goodStartItem, $goodEndItem, $goodRangeItem];
        $expectedUnavailable = [$unavailableItem, $tooEarlyItem, $tooLateItem, $tooEarlyRangeItem, $tooLateRangeItem];
    }

}
