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
@endphp
@php /** @var App\TeachingModuleItem $item */ @endphp

<div class="list-group-item list-group-item-action @unless($item->isDirectlyAvailable()) list-group-item-secondary @endif">
    <div class="d-flex w-100 justify-content-between">
        <h4>
            @unless($item->available)
                <span class="fa fa-eye-slash"
                      title="{{ __('Item unavailable to students') }}"></span>
            @endunless
            @if($item->content_id)
                <span class="fa {{ $item->content->getIcon() }}"></span>
                <a href="{{ route('modules.items.show', ['module' => $item->teaching_module_id, 'item' => $item->id]) }}">{{ $item->title }}</a>
            @else
                {{ $item->title }}
            @endif
        </h4>
        <div class="btn-group">
            @can('update', $item)
                <a class="btn btn-primary"
                   href="{{ route('modules.items.edit', ['module' => $item->teaching_module_id, 'item' => $item->id]) }}">{{ __('Edit') }}</a>
            @endcan
            @can('delete', $item)
                <button type="button" class="btn btn-danger" data-toggle="modal"
                        data-target="#deleteItemModal{{ $item->id }}">{{ __('Delete') }}</button>
            @endcan
        </div>
    </div>
    <span class="mb-0">@markdown($item->description_markdown)</span>
    @if($item->available_from and $item->available_until)
        <div><small
                class="text-muted">{{ __('Available from :start until :end', ['start' => $item->available_from, 'end' => $item->available_until ]) }}</small>
        </div>
    @elseif($item->available_from)
        <div><small
                class="text-muted">{{ __('Available from :dateTime', ['dateTime' => $item->available_from]) }}</small>
        </div>
    @elseif($item->available_until)
        <div><small
                class="text-muted">{{ __('Available until :dateTime', ['dateTime' => $item->available_until]) }}</small>
        </div>
    @endif
    @if($item->getSimpleContentType() == 'assessment' && $item->content->due_by)
        <div><small class="text-muted">{{ __('Submission due by :dateTime', ['dateTime' => $item->content->due_by ]) }}</small></div>
    @endif
</div>
@can('delete', $item)
    <x-modal-confirm :id="'deleteItemModal' . $item->id" :title="__('Delete item?')">
        <x-slot
            name="body">{{ __('Do you want to delete Item #:id (:title)?', ['id' => $item->id, 'title' => $item->title]) }}</x-slot>
        <form class="form-inline custom-control-inline mr-0"
              action="{{ route('modules.items.destroy', ['module' => $item->teaching_module_id, 'item' => $item->id]) }}"
              method="POST">
            @csrf
            @method('DELETE')
            <input class="btn btn-danger" type="submit" value="{{ __('Delete') }}"/>
        </form>
    </x-modal-confirm>
@endcan
