@php
    /**
     *  Copyright 2020-2021 Aston University
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

@php
/**
 * @var \App\TeachingModule $module
 * @var \App\TeachingModuleUser $moduleUser
 * @var \App\Http\Controllers\SubmissionsTable $submissions
 */
@endphp

@section('title'){{ __(':name in :module', ['module' => $module->name, 'name' => $moduleUser->user->name ]) }}@endsection

@section('content')
    @include('modules.users._userBreadcrumb', ['text' => $moduleUser->user->name ])

    <div class="row justify-content-center">
        <div class="col-md-12">
            <h2>{{ __('User details') }}</h2>

            <div>
                @can('update', $moduleUser)
                    <a href="{{ route('modules.users.edit', ['module' => $module->id, 'user' => $moduleUser->id]) }}"
                       class="btn btn-primary">{{ __('Edit roles') }}</a>
                @endcan
                @can('delete', $moduleUser)
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
                        {{ __('Remove') }}
                    </button>
                    <x-modal-confirm :id="'deleteModal'" :title="__('Remove User from Module?')">
                        <x-slot name="body">
                            {{ __('Do you want to remove :name from the module? This will delete all associated submissions.', ['name' => $moduleUser->user->name ]) }}
                        </x-slot>
                        <form class="form-inline custom-control-inline mr-0"
                              action="{{ route('modules.users.destroy', ['module' => $module->id, 'user' => $moduleUser->id]) }}"
                              method="POST">
                            @csrf
                            @method('DELETE')
                            <input class="btn btn-danger" type="submit" value="{{ __('Remove') }}"/>
                        </form>
                    </x-modal-confirm>
                @endcan
                <a href="{{ URL::previous() }}" class="btn btn-secondary">{{ __('Back') }}</a>
            </div>

            <table class="table mt-4">
                <tbody>
                <tr>
                    <th scope="row">{{ __('Email') }}</th>
                    <td><a href="mailto:{{ $moduleUser->user->email }}">{{ $moduleUser->user->email }}</a></td>
                </tr>
                <tr>
                    <th scope="row">{{ __('Roles') }}</th>
                    <td>{{ implode(', ', $moduleUser->getCleanRoleNames()) }}
                </tr>
                <tr>
                    <th scope="row">{{ __('Enrolled at') }}</th>
                    <td>{{ $moduleUser->created_at }}
                </tr>
                </tbody>
            </table>

            @if(!is_null($submissions))
                @if($submissions->submissions->isEmpty())
                    <div class="alert alert-warning mt-2" role="alert">
                        {{ __('This user has not made any submissions in this module yet.') }}
                    </div>
                @else
                    <h3>
                        {{ __('Submissions') }}
                    </h3>
                    <div class="btn-toolbar mb-2" role="toolbar" aria-label="Folder items toolbar">
                        <a href="{{ route('modules.users.show', ['module' => $module->id, 'user' => $moduleUser->id, \App\Http\Controllers\SubmissionsTable::SHOW_LATEST_KEY => !$submissions->showLatest ]) }}"
                           class="btn btn-primary mr-2">{{ $submissions->showLatest ? __('Show all attempts') : __('Show last attempts') }}</a>
                    </div>

                    @include('components.submissions-table', ['table' => $submissions])
                @endif
            @endif
        </div>
    </div>
@endsection
