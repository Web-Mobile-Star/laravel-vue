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

@section('title', __('Import users'))

@php /** @var \App\TeachingModule $module */ @endphp

@section('content')
    @include('modules.users._userBreadcrumb', ['text' => __('Import from CSV')])

    <div class="row justify-content-center">
        <div class="col-md-6">
            <p>
                {{ __('To import users from a CSV file, make sure that it is comma-delimited and that it has a "Username" column matching their LDAP usernames.') }}
            </p>

            <p>
                {{__('To produce such a CSV file from Blackboard, follow these steps:')}}
            </p>

            <ol>
                <li>{{ __('Go to the Grade Centre, and select Work Offline - Download.') }}</li>
                <li>{{ __('Select "Student Information", and pick "Comma" as the separator.') }}</li>
                <li>{{ __('Download the file and select it in this form.') }}</li>
            </ol>

            <form method="POST" action="{{ route('modules.users.import', $module->id) }}" enctype="multipart/form-data">
                @csrf
                <x-file-upload-field :fieldName="'csvfile'" :required="true" :promptText="'Choose CSV file'"
                                     :accept="'text/csv, .csv'"/>
                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                    <a role="button" class="btn btn-secondary"
                       href="{{ route('modules.users.index', $module->id) }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
