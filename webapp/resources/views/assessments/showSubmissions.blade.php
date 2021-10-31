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

@php
    /**
     * @var \App\TeachingModule $module
     * @var \App\Assessment $assessment
     * @var \App\Http\Controllers\SubmissionsTable $submissions
     * @var string $achievable
     */
@endphp

@section('title', __('Submissions - :title', ['title' => $assessment->usage->title]))

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module" :item="$assessment->usage">
                <li class="breadcrumb-item active">{{ __('Submissions') }}</li>
            </x-item-path>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-12">
            @if($submissions->submissions->isEmpty())
                <div class="alert alert-warning mt-2" role="alert">
                    {{ __('No submissions have been made yet to this assessment.') }}
                </div>
            @else
                <h3>
                    {{ __('Submissions') }}
                </h3>
                <div class="btn-toolbar mb-2" role="toolbar" aria-label="Folder items toolbar">
                    @can('compare', $assessment)
                        <a href="{{ route('modules.assessments.compare.showCSVForm', ['module' => $module->id, 'assessment' => $assessment->id ]) }}"
                           class="btn btn-primary mr-2">{{ __('Compare') }}</a>
                    @endcan
                    <a href="{{ route('modules.assessments.showSubmissions', ['module' => $module->id, 'assessment' => $assessment->id, \App\Http\Controllers\SubmissionsTable::SHOW_LATEST_KEY => !$submissions->showLatest ]) }}"
                       class="btn btn-primary mr-2">{{ $submissions->showLatest ? __('Show all attempts') : __('Show last attempts') }}</a>
                    <a href="{{ route('modules.assessments.downloadSubmissionsCSV', ['module' => $module->id, 'assessment' => $assessment->id, \App\Http\Controllers\SubmissionsTable::SHOW_LATEST_KEY => $submissions->showLatest ]) }}"
                       class="btn btn-primary">{{ __('Export to CSV') }}</a>
                </div>

                @include('components.submissions-table', ['table' => $submissions])
            @endif
        </div>
    </div>
@endsection
