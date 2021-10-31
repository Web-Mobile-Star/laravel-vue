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

@section('title', __('Comparison Results - :title', ['title' => $assessment->usage->title]))

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
                <li class="breadcrumb-item"><a href="{{ route('modules.assessments.compare.showCSVForm', ['module' => $module, 'assessment' => $assessment ]) }}">{{ __('Compare') }}</a></li>
                <li class="breadcrumb-item active">{{ __('Results') }}</li>
            </x-item-path>

            <h3 class="mt-4 text-center">{{ __('Comparison results') }}</h3>

            <h5 class="text-center mt-2">{{ __('Different files (:count)', ['count' => count($differentExternal) ]) }}</h5>
            @if($differentExternal)
            <div class="table-responsive">
                <table class="table table-hover" id="af-compare-df-table">
                    <thead>
                    <tr>
                        <th scope="col">{{ __('Author') }}</th>
                        <th scope="col">{{ __('Submission ID') }}</th>
                        <th scope="col">{{ __('External Filename') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($differentExternal as $row)
                        <tr>
                            <td>{{ $row[App\Assessment::COMPARE_USER]->user->name  }}</td>
                            @if(array_key_exists(App\Assessment::COMPARE_ASSESSMENT_SUBMISSION, $row))
                            <td><a href="{{ route('modules.submissions.show', ['module' => $module, 'submission' => $row[App\Assessment::COMPARE_ASSESSMENT_SUBMISSION] ]) }}">{{ $row[App\Assessment::COMPARE_ASSESSMENT_SUBMISSION]->id }}</a></td>
                            @else
                            <td>N/A</td>
                            @endif
                            <td class="af-compare-df-filename">{{ $row[App\Assessment::COMPARE_FILENAME] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-center">
                <a href="#" class="btn btn-primary" onclick="DownloadColumn('#af-compare-df-table .af-compare-df-filename', 'different-files-a{{ $assessment->id }}.txt')">{{ __('Download list of filenames') }}</a>
            </div>
            @else
                <p class="alert alert-success">{{ __('No different SHA-256 checksums have been found in the external CSV file.') }}</p>
            @endif

            <h5 class="text-center mt-4">{{ __('Not in external CSV (:count)', ['count' => count($notInExternal)]) }}</h5>
            @if($notInExternal)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('Author') }}</th>
                            <th scope="col">{{ __('Email') }}</th>
                            <th scope="col">{{ __('Submission ID') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($notInExternal as $row)
                            <tr>
                                <td>{{ $row[App\Assessment::COMPARE_USER]->user->name }}</td>
                                <td><a href="mailto:{{ $row[App\Assessment::COMPARE_USER]->user->email }}">{{ $row[App\Assessment::COMPARE_USER]->user->email }}</a></td>
                                <td><a href="{{ route('modules.submissions.show', ['module' => $module, 'submission' => $row[App\Assessment::COMPARE_ASSESSMENT_SUBMISSION] ]) }}">{{ $row[App\Assessment::COMPARE_ASSESSMENT_SUBMISSION]->id }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="alert alert-success">{{ __('The external CSV does not have any AutoFeedback users which have not submitted to AutoFeedback.') }}</p>
            @endif

            <h5 class="text-center mt-4">{{ __('Not enrolled (:count)', ['count' => count($notInModule)]) }}</h5>
            @if($notInModule)
                <ul>
                    @foreach($notInModule as $email)
                        <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                    @endforeach
                </ul>
            @else
                <p class="alert alert-success">{{ __('The external CSV does not list any emails which are not enrolled in this module.') }}</p>
            @endif

        </div>
    </div>
@endsection
