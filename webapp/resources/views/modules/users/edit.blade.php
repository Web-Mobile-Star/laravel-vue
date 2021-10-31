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

@section('title')
    {{ __('Edit User') }}
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module">
                @can('viewUsers', $module)
                    <li class="breadcrumb-item"><a href="{{ route('modules.users.index', ['module' => $module->id]) }}">{{ __('Users') }}</a></li>
                @else
                    <li class="breadcrumb-item">{{ __('Users') }}</li>
                @endcan
                @can('view', $moduleUser)
                    <li class="breadcrumb-item"><a href="{{ route('modules.users.show', ['module' => $module->id, 'user' => $moduleUser->id]) }}">{{ $moduleUser->user->name }}</a></li>
                @else
                    <li class="breadcrumb-item">{{ $moduleUser->user->name }}</li>
                @endcan
                <li class="breadcrumb-item active">{{ __('Edit User') }}</li>
            </x-item-path>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST"
                  action="{{ route('modules.users.update', ['module' => $module->id, 'user' => $moduleUser->id]) }}">
                @csrf
                @method('PUT')
                @include('modules.users._roleSelector')

                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                    <a role="button" class="btn btn-secondary"
                       href="{{ route('modules.users.show', ['module' => $module->id, 'user' => $moduleUser->id]) }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
