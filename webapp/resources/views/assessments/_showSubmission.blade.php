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

    /**
     * @var App\TeachingModule $module
     * @var ?App\AssessmentSubmission $submission
     * @var boolean $byClass
     */
@endphp
@if($submission)
    <h3 class="mt-4 text-center">{{ __('Submission details') }}</h3>
    <table class="table">
        <tbody>
        <tr>
            <th scope="row">{{ __('Submission') }}</th>
            <td>
                <div class="dropdown show">
                    @can('view', $submission->submission)
                        <a href="{{ route('jobs.show', $submission->submission->id) }}">{{ $submission->submission->filename }}</a>
                    @else
                        {{ $submission->submission->filename }}
                    @endcan
                    @if($submission->attempts()->count() == 1)
                        {{ __('(attempt #:attempt)', ['attempt' => $submission->attempt ]) }}
                    @else
                        <a class="btn btn-sm btn-secondary dropdown-toggle ml-1" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            {{ __('Attempt #:attempt', ['attempt' => $submission->attempt]) }}
                        </a>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                            @foreach($submission->attempts as $attempt)
                                <a class="dropdown-item" href="{{ route('modules.submissions.show', ['module' => $module->id, 'submission' => $attempt->id]) }}">{{
                                  __('Attempt #:attempt at :updated (:points)', [
                                      'attempt' => $attempt->attempt,
                                      'points' => $attempt->points,
                                      'updated' => $attempt->updated_at
                                      ]) }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row">{{ __('Author') }}</th>
            @can('view', $submission->author)
                <td><a href="{{ route('modules.users.show', ['module' => $module->id, 'user' => $submission->author->id ]) }}">{{ $submission->author->user->name }}</a> - <a href="mailto:{{ $submission->author->user->email }}">{{ $submission->author->user->email }}</a></td>
            @else
            <td>{{ $submission->author->user->name }} - <a href="mailto:{{ $submission->author->user->email }}">{{ $submission->author->user->email }}</a></td>
            @endcan
        </tr>
        <tr>
            <th scope="row">{{ __('Status') }}</th>
            <x-job-status-cell :job="$submission->submission"/>
        </tr>
        <tr>
            <th scope="row">{{ __('Created at') }}</th>
            <td>
                {{ $submission->created_at }}
                @if($submission->isLate())
                    <span class="badge badge-warning" data-toggle="tooltip" title="{{ __('Deadline was :deadline', ['deadline' => $submission->assessment->due_by ]) }}" >{{ __('late') }}</span>
                @endif
            </td>
        </tr>
        @if($submission->modelSolution)
        <tr>
            <th scope="row">{{ __('Model solution') }}</th>
            <td>
                <x-model-solution-version-description :modelSolution="$submission->modelSolution"/>
            </td>
        </tr>
        @endif
        </tbody>
    </table>
    @if($submission->points === \App\AssessmentSubmission::POINTS_PENDING)
        <div class="alert alert-warning af-aborted-job-reload af-marks-reload" data-af-job-id="{{ $submission->submission->id }}" data-af-asub-id="{{ $submission->id }}">{{
          __('Your submission is being checked: please wait. The submission will go from "Pending" (waiting in the queue) to "Running", and then to "Failed" (your code did not compile, or some test did not pass) or "Completed" (all tests passed). The page will reload automatically with the results. You can also leave this page and come back to it later.')
        }}</div>
    @else
        <h3 class="mt-4 text-center">{{ __('Submission results') }}</h3>
        <table class="table">
            <tbody>
            <tr>
                <th scope="row">{{ __('Overall mark') }}</th>
                <td>{{ $submission->points }} out of {{ $submission->assessment->achievablePoints() }}</td>
            </tr>
            <tr>
                <th scope="row">{{ __('Summary') }}</th>
                <td>
                    {{ __(':np passed, :nf failed, :ne with errors, :ns skipped, :nm missing', ['np' => $submission->passed, 'nf' => $submission->failed, 'ne' => $submission->errors, 'ns' => $submission->skipped, 'nm' => $submission->missing ]) }}
                    @if( $submission->assessment->hasTasks())
                    <a href="{{ route('modules.submissions.show', ['module' => $module->id, 'submission' => $submission->id, 'byClass' => !$byClass ]) }}" class="btn-sm btn-primary float-right">{{ __($byClass ? 'Grouped by test class' : 'Grouped by task') }}</a>
                    @endif
                </td>
            </tr>
            @if($submission->missing > 0 && $submission->submission->stdoutResult())
            <tr><td colspan="2">
                <div class="alert alert-warning">
                     {{ __('Some of the expected test results are missing: you may have compilation errors, or may be missing files in your submission.') }}
                     <a target="_blank" href="{{ $submission->submission->stdoutResult()->url($submission->submission) }}">{{ __('Check the detailed output for your submission here.') }}</a>
                </div>
            </td></tr>
            @endif
            </tbody>
        </table>
        @if($byClass || !$submission->assessment->hasTasks())
            @foreach($submission->junitTestSuites() as $suite)
                @include('assessments._showAssessmentTestSuite', ['suite' => $suite, 'idPrefix' => 'junitts' . $loop->index, 'missing' => false, 'showClassRows' => false ])
            @endforeach
            @foreach($submission->missingTestSuites() as $suite)
                @include('assessments._showAssessmentTestSuite', ['suite' => $suite, 'idPrefix' => 'missingts' . $loop->index, 'missing' => true, 'showClassRows' => false ])
            @endforeach
        @else
            @foreach($submission->taskTestSuites() as $suite)
                @include('assessments._showAssessmentTestSuite', ['suite' => $suite, 'idPrefix' => 'taskts' . $loop->index, 'missing' => false, 'showClassRows' => true ])
            @endforeach
        @endif
    @endif
@else
    <div class="alert alert-warning mt-4">{{ __('No submission has been uploaded yet.') }}</div>
@endif
