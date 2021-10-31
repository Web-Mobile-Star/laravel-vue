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

@extends('layouts.app')

@php /** @var \App\TeachingModule $module */ @endphp
@php /** @var \App\TeachingModule $moduleUser */ @endphp
@php /** @var \Spatie\Permission\Models\Role $activeRole */ @endphp
@php /** @var \Illuminate\Database\Eloquent\Collection $items */ @endphp
@php /** @var boolean $isAdmin */ @endphp

@section('title')Module {{ $module->name }}@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2>{{ $module->name }}</h2>

            <div class="btn-toolbar" role="toolbar" aria-label="{{ __('Module users toolbar') }}">
                @if ($moduleUser and ($moduleUser->roles()->count() > 1 || $isAdmin))
                    <form method="POST" class="form-inline" action="{{ route('modules.switchRole', $module->id) }}">
                        @csrf
                        <div class="btn-group mr-2" role="group" aria-label="{{ __('Role switcher') }}">
                            @foreach ($moduleUser->roles()->get() as $role)
                                <button type="submit" name="role" value="{{ $role->id }}"
                                        class="btn btn-outline-success @if ($activeRole && $role->id == $activeRole->id) active @endif">
                                    {{ \App\Policies\TeachingModuleUserPolicy::cleanRoleName($role->name) }}
                                </button>
                            @endforeach
                            @if($isAdmin)
                                <button type="submit" name="role" value="unset"
                                        class="btn btn-outline-success @if (is_null($activeRole)) active @endif">
                                    {{ __('Admin') }}
                                </button>
                            @endif
                        </div>
                    </form>
                @endif
                @can('viewUsers', $module)
                    <div class="btn-group mr-2" role="group">
                        <a href="{{ route('modules.users.index', $module->id) }}"
                           class="btn btn-primary">{{ __('Users') }}</a>
                    </div>
                @endcan
                @can('update', $module)
                    <div class="btn-group mr-2" role="group">
                        <a href="{{ route('modules.edit', $module->id) }}" class="btn btn-primary">{{ __('Edit') }}</a>
                    </div>
                @endcan
                @can('delete', $module)
                    <div class="btn-group mr-2" role="group">
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
                            {{ __('Delete') }}
                        </button>
                    </div>
                    <x-modal-confirm :id="'deleteModal'" :title="__('Delete module?')">
                        <x-slot name="body">
                            {{ __('Do you want to delete Module #:id and all its enrolments and submissions?', ['id' => $module->id]) }}
                        </x-slot>
                        <form class="form-inline custom-control-inline mr-0"
                              action="{{ route('modules.destroy', $module->id) }}"
                              method="POST">
                            @csrf
                            @method('DELETE')
                            <input class="btn btn-danger" type="submit" value="{{ __('Delete') }}"/>
                        </form>
                    </x-modal-confirm>
                @endcan
            </div>
        </div>
    </div>
    <div class="row justify-content-center mt-4">
        <div class="col-md-8">
            <h3>Module content</h3>
            <div class="btn-toolbar" role="toolbar" aria-label="{{ __('Module items toolbar') }}">
                @can('createItem', $module)
                <a class="btn btn-primary mr-2"
                   href="{{ route('modules.items.create', $module->id) }}">{{ __('Create Item') }}</a>
                <a class="btn btn-primary mr-2"
                   href="{{ route('modules.items.create', ['module' => $module->id, 'type' => 'folder']) }}">{{ __('Create Folder') }}</a>
                <a class="btn btn-primary"
                   href="{{ route('modules.items.create', ['module' => $module->id, 'type' => 'assessment']) }}">{{ __('Create Assessment') }}</a>
                @endcan
            </div>
            <x-teaching-module-item-list :items="$items"/>
        </div>
    </div>
@endsection
