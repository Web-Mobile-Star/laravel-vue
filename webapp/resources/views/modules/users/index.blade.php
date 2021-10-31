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

@php /** @var \App\TeachingModule|Illuminate\Pagination\LengthAwarePaginator $module */ @endphp
@php /** @var \App\TeachingModuleUser[]|Illuminate\Pagination\LengthAwarePaginator $users */ @endphp

@section('title', 'Users in ' . $module->name)

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module">
                <li class="breadcrumb-item active">{{ __('Users') }}</li>
            </x-item-path>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div>
                @can('enrolUsers', $module)
                    <a class="btn btn-primary"
                       href="{{ route('modules.users.importForm', $module->id) }}">{{ __('Import Users') }}</a>
                    <a class="btn btn-primary"
                       href="{{ route('modules.users.create', $module->id) }}">{{ __('Enrol User') }}</a>
                @endcan
                @can('removeUsers', $module)
                    <button id="deleteManyModalButton" type="button" class="btn btn-danger" data-toggle="modal"
                            data-target="#deleteManyModal" data-af-row-select-table="#usersTable">
                        {{ __('Remove selected') }}
                    </button>

                    <x-modal-confirm :id="'deleteManyModal'" :title="__('Remove checked users from the module?')">
                        <x-slot name="body">
                            {{ __('Do you wish to remove the checked users from the module? This will also delete their submissions.') }}
                        </x-slot>
                        <form class="form-inline custom-control-inline mr-0"
                              action="{{ route('modules.users.destroyMany', $module->id) }}"
                              method="POST" id="deleteManyForm">
                            @csrf
                            @method('DELETE')
                            <input class="btn btn-danger" type="submit" value="{{ __('Remove') }}"/>
                        </form>
                    </x-modal-confirm>
                @endcan
                <a class="btn btn-secondary" href="{{ route('modules.show', $module->id ) }}">{{ __('Back') }}</a>
            </div>

            @if($users->count() > 0)
                <table class="table table-hover mt-4 af-row-select-table" id="usersTable">
                    <thead>
                    <th scope="col"><input type="checkbox" class="af-row-select-all"/></th>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="email">{{ __('Email') }}</th>
                    <th scope="col">{{ __('Roles') }}</th>
                    </thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td><input type="checkbox" class="af-row-select" value="{{ $u->id }}"/></td>
                            <td>
                                <a href="{{ route('modules.users.show', ['module' => $module->id, 'user' => $u->id])  }}">{{ $u->user->name }}</a>
                            </td>
                            <td><a href="mailto:{{ $u->user->email }}">{{ $u->user->email  }}</a></td>
                            <td>{{ implode(', ', $u->getCleanRoleNames()) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{ $users->links() }}
            @else
                <div class="alert alert-warning mt-2" role="alert">
                    {{ __('No users have been assigned yet to this module.') }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('extrajs')
    <script type="application/javascript">
        $('#deleteManyForm').on('submit', () => {
            $('#usersTable input.af-row-select:checked').each((i, e) => {
                $('<input type="hidden">').attr({
                    name: 'ids[]',
                    value: e.value
                }).appendTo('#deleteManyForm');
            });
            return true;
        });
    </script>
@endsection
