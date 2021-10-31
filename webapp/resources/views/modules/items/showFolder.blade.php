@php
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

/**
 * @var App\TeachingModuleItem $item
 * @var App\TeachingModule $module
 * @var App\Folder $folder
 * @var \Illuminate\Database\Eloquent\Collection $children
 */
@endphp

<div class="btn-toolbar" role="toolbar" aria-label="{{ __('Folder items toolbar') }}">
    @can('createItem', $module)
        <a class="btn btn-primary mr-2"
           href="{{ route('modules.items.create', ['module' => $module->id, 'folder' => $item->content->id]) }}">{{ __('Create Item') }}</a>
        <a class="btn btn-primary mr-2"
           href="{{ route('modules.items.create', ['module' => $module->id, 'type' => 'folder', 'folder' => $item->content->id]) }}">{{ __('Create Folder') }}</a>
        <a class="btn btn-primary mr-2"
           href="{{ route('modules.items.create', ['module' => $module->id, 'type' => 'assessment', 'folder' => $item->content->id]) }}">{{ __('Create Assessment') }}</a>
    @endcan
</div>

<x-teaching-module-item-list :items="$children"/>
