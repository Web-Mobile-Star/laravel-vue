@php
    /**
     *  Copyright 2021 Aston University
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

@section('title', __('Upload CSV for Comparison - :title', ['title' => $assessment->usage->title]))

@php
/**
 * @var \App\TeachingModule $module
 * @var \App\Assessment $assessment
 * @var array $columns
 */
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module" :item="$assessment->usage">
                <li class="breadcrumb-item"><a href="{{ route('modules.assessments.showSubmissions', ['module' => $module, 'assessment' => $assessment ]) }}">{{ __('Submissions') }}</a></li>
                <li class="breadcrumb-item active">{{ __('Compare') }}</li>
            </x-item-path>

            <h3 class="mt-4 text-center">{{ __('Compare submissions with external system') }}</h3>

            <p>
                {{ __('This form is for comparing the AutoFeedback submissions with those of an external system (e.g. a virtual learning environment).') }}
                {{ __('You must supply a CSV file with the following columns:') }}
                <ul>
                    @foreach($columns as $name => $desc)
                        <li>{{ $name }}: {{ $desc }}</li>
                    @endforeach
                </ul>
            </p>

            <form method="POST" action="{{ route('modules.assessments.compare.processCSV', ['module' => $module->id, 'assessment' => $assessment->id]) }}" enctype="multipart/form-data">
                @csrf
                <x-file-upload-field :fieldName="'csvfile'" :required="true" :promptText="'Choose CSV file'"
                                     :accept="'text/csv,.csv'"/>
                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                </div>
            </form>
        </div>
    </div>
@endsection
