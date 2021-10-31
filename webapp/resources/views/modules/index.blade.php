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

@php /** @var \App\TeachingModule[]|Illuminate\Pagination\LengthAwarePaginator $modules */ @endphp

@section('title', 'Modules')

@section('content')
        <div class="row justify-content-center">
            <div class="col-md-8">
                @can('create', \App\TeachingModule::class)
                    <a role="button" class="btn btn-primary" href="{{ route('modules.create') }}">Create Module</a>
                @endcan

                @if($modules->count() > 0)
                    <div class="list-group mt-4">
                        @foreach($modules as $module)
                            <a class="list-group-item list-group-item-action text-primary"
                               href="{{ route('modules.show', $module->id) }}">{{ $module->name }}</a>
                        @endforeach
                    </div>
                    {{ $modules->links() }}
                @else
                    <div class="alert alert-warning mt-2" role="alert">
                        {{ __('No modules have been created yet, or you are not part of any modules.') }}
                    </div>
                @endif
            </div>
        </div>
@endsection
