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

namespace App\Http\Controllers;

use App\Assessment;
use App\Folder;
use App\Rules\ContentBelongsToModule;
use App\TeachingModule;
use App\TeachingModuleItem;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeachingModuleItemController extends Controller
{
    const DATETIME_FORMAT = 'd/m/Y H:i';

    const VALID_ITEM_TYPES = ['folder', 'assessment'];

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @return View
     * @throws AuthorizationException
     */
    public function create(Request $request, TeachingModule $module)
    {
        $this->authorize('createItem', $module);

        $item = new TeachingModuleItem;
        $item->available = true;
        $item->teaching_module_id = $module->id;

        $folder = $request->get('folder');
        $folder = !is_null($folder) ? intval($folder) : null;
        $container = null;
        if ($folder) {
            $container = Folder::find($folder)->usage;
        }

        $itemType = $request->get('type');
        if (!in_array($request->get('type'), self::VALID_ITEM_TYPES)) {
            $itemType = null;
        }

        return view('modules.items.create', [
            'module' => $module,
            'item' => $item,
            'container' => $container,
            'dateTimeFormat' => self::DATETIME_FORMAT,
            'itemType' => $itemType,
            'folder' => $folder,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function store(Request $request, TeachingModule $module)
    {
        $this->authorize('createItem', $module);
        $v = $this->createItemValidator($request, $module);
        if ($v->fails()) {
            return redirect(URL::previous())->withErrors($v)->withInput();
        }

        $item = new TeachingModuleItem;
        $item->teaching_module_id = $module->id;
        if ($request->has('itemFolder')) {
            $item->folder_id = intval($request->get('itemFolder'));
        }
        $this->updateFieldsFromRequest($request, $item);
        $item->save();

        // Create content
        $itemType = $request->get('itemType');
        if ($itemType === 'folder') {
            /** @var Folder $folder */
            $folder = Folder::create();
            $folder->usage()->save($item);
        } else if ($itemType === 'assessment') {
            /** @var Assessment $assessment */
            $assessment = Assessment::create();
            $dueBy = $request->get('dueBy');
            if ($dueBy) {
                $assessment->due_by = Carbon::createFromFormat(self::DATETIME_FORMAT, $dueBy);
                $assessment->save();
            }
            $assessment->usage()->save($item);
        }

        return $this->redirectFromChange($module, $item)
            ->with('status', __('Created item successfully.'));
    }

    /**
     * Display the specified resource.
     *
     * @param TeachingModule $module
     * @param TeachingModuleItem $item
     * @return RedirectResponse|View
     * @throws AuthorizationException
     */
    public function show(TeachingModule $module, TeachingModuleItem $item)
    {
        $this->authorize('view', $item);
        $this->authorizeNesting($module, $item);

        if ($item->teaching_module_id !== $module->id) {
            return redirect()
                ->route('modules.show', $module->id)
                ->with('warning', __('The specified item does not belong to this module. Redirecting to module view.'));
        } else if (is_null($item->content)) {
            if (is_null($item->folder)) {
                return redirect()
                    ->route('modules.show', $module->id)
                    ->with('warning', __('The specified item has no content.'));
            } else {
                return redirect()
                    ->route('modules.items.show', ['module' => $module->id, 'item' => $item->folder->usage->id])
                    ->with('warning', __('The specified item has no content.'));
            }
        }

        return view('modules.items.show', [
            'module' => $module,
            'item' => $item,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param TeachingModule $module
     * @param TeachingModuleItem $item
     * @return View
     * @throws AuthorizationException
     */
    public function edit(TeachingModule $module, TeachingModuleItem $item)
    {
        $this->authorize('update', $item);
        $this->authorizeNesting($module, $item);
        return view('modules.items.create', [
            'module' => $module,
            'item' => $item,
            'dateTimeFormat' => self::DATETIME_FORMAT,
            'itemType' => $item->getSimpleContentType()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @param TeachingModuleItem $item
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function update(Request $request, TeachingModule $module, TeachingModuleItem $item)
    {
        $this->authorize('update', $item);
        $this->authorizeNesting($module, $item);

        $v = $this->createItemValidator($request, $module);
        if ($v->fails()) {
            return redirect(URL::previous())->withErrors($v)->withInput();
        }

        $this->updateFieldsFromRequest($request, $item);
        if ($item->getSimpleContentType() == 'assessment') {
            $dueBy = $request->get('dueBy');
            if ($dueBy) {
                $item->content->due_by = Carbon::createFromFormat(self::DATETIME_FORMAT, $dueBy);
            } else {
                $item->content->due_by = null;
            }
            $item->content->save();
        }
        $item->save();
        return $this->redirectFromChange($module, $item)
            ->with('status', 'Item updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TeachingModule $module
     * @param TeachingModuleItem $item
     * @return RedirectResponse
     * @throws AuthorizationException The user is not allowed to delete the item.
     * @throws \Exception Could not delete the item.
     */
    public function destroy(TeachingModule $module, TeachingModuleItem $item)
    {
        $this->authorize('delete', $item);
        $this->authorizeNesting($module, $item);
        $item->delete();
        return $this->redirectFromChange($module)
            ->with('status', 'Item deleted successfully.');
    }

    /**
     * @param Request $request
     * @param TeachingModule $module
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function createItemValidator(Request $request, TeachingModule $module): \Illuminate\Contracts\Validation\Validator
    {
        $v = Validator::make($request->all(), [
            'itemTitle' => ['required', 'string'],
            'itemDescription' => ['required', 'string'],
            'itemAvailable' => 'nullable',
            'itemAvailableStart' => ['nullable', "date_format:" . self::DATETIME_FORMAT],
            'itemAvailableEnd' => ['nullable', "date_format:" . self::DATETIME_FORMAT],
            'itemType' => ['nullable', Rule::in(self::VALID_ITEM_TYPES)],
            'itemFolder' => ['nullable', 'integer', 'exists:folders,id', new ContentBelongsToModule($module)],
            'dueBy' => ['nullable', "date_format:" . self::DATETIME_FORMAT],
        ]);
        $v->sometimes('itemAvailableStart', 'before_or_equal:itemAvailableEnd', function ($input) {
            return $input->has('itemAvailableEnd') && strlen(trim($input->get('itemAvailableEnd'))) > 0;
        });
        $v->sometimes('itemAvailableStart', 'before_or_equal:dueBy', function ($input) {
            return $input->has('dueBy') && strlen(trim($input->get('dueBy'))) > 0;
        });
        $v->sometimes('itemAvailableEnd', 'after_or_equal:itemAvailableStart', function ($input) {
            return $input->has('itemAvailableStart') && strlen(trim($input->get('itemAvailableStart'))) > 0;
        });
        $v->sometimes('itemAvailableEnd', 'after_or_equal:dueBy', function ($input) {
            return $input->has('dueBy') && strlen(trim($input->get('dueBy'))) > 0;
        });
        return $v;
    }

    /**
     * @param Request $request
     * @param TeachingModuleItem $item
     */
    private function updateFieldsFromRequest(Request $request, TeachingModuleItem $item): void
    {
        $item->title = $request->get('itemTitle');
        $item->description_markdown = $request->get('itemDescription');
        $item->available = boolval($request->get('itemAvailable'));

        if ($request->get('itemAvailableStart')) {
            $item->available_from = Carbon::createFromFormat(self::DATETIME_FORMAT, $request->get('itemAvailableStart'));
        } else {
            $item->available_from = null;
        }

        if ($request->get('itemAvailableEnd')) {
            $item->available_until = Carbon::createFromFormat(self::DATETIME_FORMAT, $request->get('itemAvailableEnd'));
        } else {
            $item->available_until = null;
        }
    }

    /**
     * @param TeachingModule $module
     * @param TeachingModuleItem|null $item
     * @return RedirectResponse
     */
    private function redirectFromChange(TeachingModule $module, TeachingModuleItem $item = null): RedirectResponse
    {
        if (is_null($item) || is_null($item->folder_id)) {
            return redirect()->route('modules.show', $module->id);
        } else {
            return redirect()->route('modules.items.show', [
                'module' => $module->id,
                'item' => $item->folder->usage->id,
            ]);
        }
    }

    /**
     * @param TeachingModule $module
     * @param TeachingModuleItem $item
     * @throws AuthorizationException
     */
    private function authorizeNesting(TeachingModule $module, TeachingModuleItem $item): void
    {
        if ($module->id !== $item->teaching_module_id) {
            throw new AuthorizationException("The item must belong to the teaching module.");
        }
    }
}
