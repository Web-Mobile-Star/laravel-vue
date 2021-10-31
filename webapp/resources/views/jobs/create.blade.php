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

@section('title', __('Create Raw Job'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="text-center">{{ __('Create Raw Job') }}</h2>
            <form method="POST" action="{{ route('jobs.store') }}" enctype="multipart/form-data">
                @csrf
                <x-file-upload-field :fieldName="'jobfile'" :required="true" :promptText="'Choose ZIP file'"
                                     :accept="'application/zip,.zip'"/>
                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                    <a role="button" class="btn btn-secondary" href="{{ route('jobs.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
