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
 * @var \App\AssessmentSubmission $submission
 * @var boolean $byClass
 */
@endphp

@section('title'){{ __('Submission :id', ['id' => $submission->id ]) }}@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module" :item="$submission->assessment->usage">
                @can('viewSubmissions', $submission->assessment)
                <li class="breadcrumb-item"><a href="{{ route('modules.assessments.showSubmissions', ['module' => $module->id, 'assessment' => $submission->assessment->id ]) }}">{{ __('Submissions') }}</a></li>
                @else
                <li class="breadcrumb-item">{{ __('Submissions') }}</li>
                @endcan
                <li class="breadcrumb-item active">{{ __('Submission :id', ['id' => $submission->id ]) }}</li>
            </x-item-path>

            @include('assessments._showSubmission', [ 'module' => $module, 'submission' => $submission, 'byClass' => $byClass ])
            @include('assessments._rerunSubmission', [ 'submission' => $submission, 'keepLatest' => Config::get(\App\Assessment::CONFIG_KEEP_LATEST_ATTEMPTS) ])
            @include('assessments._deleteSubmission', [ 'submission' => $submission ])
        </div>
    </div>
@endsection
