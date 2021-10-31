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

@section('title', __('Model solution - :title', ['title' => $assessment->usage->title]))

@php
/**
 * @var \App\TeachingModule $module
 * @var \App\Assessment $assessment
 * @var \App\ZipSubmission $submission
 * @var int $countSubmissions
 * @var int $countOutdated
 * @var int $countFullMarks
 * @var int $countMissing
 */
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module" :item="$assessment->usage">
                <li class="breadcrumb-item active">{{ __('Model solution') }}</li>
            </x-item-path>

            @if($assessment->modelSolutions->count() > 0)
                <h3 class="mt-4 text-center">{{ __('Available model solutions') }}</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <th scope="col">{{ __('Submission') }}</th>
                        <th scope="col">{{ __('Author') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Created at') }}</th>
                        <th scope="col">{{ __('Version') }}</th>
                        </thead>
                        <tbody>
                        @foreach($assessment->modelSolutions->sortBy('version') as $s)
                            <tr>
                                <td><a href="{{ route('jobs.show', $s->submission->id) }}">{{ $s->submission->id }}</a></td>
                                <td>{{ $s->submission->user->name }}</td>
                                <x-job-status-cell :job="$s->submission"/>
                                <td>{{ $s->submission->created_at }}</td>
                                <td>{{ $s->version }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @can('uploadModelSolution', $assessment)
            @if($assessment->latestModelSolution)
            <h3 class="mt-4 text-center">{{ __('Update model solution') }}</h3>
            @else
            <h3 class="mt-4 text-center">{{ __('Upload model solution') }}</h3>
            @endif
            <form method="POST" action="{{ route('modules.assessments.storeModelSolution', ['module' => $module->id, 'assessment' => $assessment->id]) }}" enctype="multipart/form-data">
                @csrf
                <x-file-upload-field :fieldName="'jobfile'" :required="true" :promptText="'Choose ZIP file'"
                                     :accept="'application/zip,.zip'"/>
                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                </div>
            </form>
            @endcan

            @can('deleteModelSolution', $assessment)
                @if($assessment->latestModelSolution)
                    <h3 class="mt-4 text-center">{{ __('Clear latest model solution') }}</h3>
                    <form
                        action="{{ route('modules.assessments.destroyModelSolution', ['module' => $module->id, 'assessment' => $assessment->id ]) }}"
                        method="POST">
                        @csrf
                        @method('DELETE')
                        <div class="text-center mt-2">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input af-check-enable-button" id="confirmDelete"
                                       data-af-button="#btnSubmitDelete"/>
                                <label for="confirmDelete" class="form-check-label">{{ __('Are you sure?') }}</label>
                            </div>
                            <input class="btn btn-danger" type="submit" value="{{ __('Delete') }}" id="btnSubmitDelete" disabled>
                        </div>
                    </form>
                @endif
            @endcan

            @can('rerunSubmissions', $assessment)
                @if($assessment->latestModelSolution)
                    <h3 class="mt-4 text-center">{{ __('Rerun submissions') }}</h3>
                    <form
                        action="{{ route('modules.assessments.rerunSubmissions', ['module' => $module->id, 'assessment' => $assessment->id ]) }}"
                        method="POST">
                        @csrf
                        <div class="mt-2 col-md-6 offset-md-3">
                            <div class="form-group">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="rerunInclude" id="rerunIncludeAll" value="all" checked />
                                    <label class="form-check-label" for="rerunIncludeAll">{{ __('Include all :count :word', ['count' => $countSubmissions, 'word' => Str::plural(__('submission'), $countSubmissions) ]) }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="radio" class="form-check-input" name="rerunInclude" id="rerunIncludeOutdated" value="outdated"/>
                                    <label for="rerunIncludeOutdated" class="form-check-label">{{ __('Include only the :count :word older than the current model solution', ['count' => $countOutdated, 'word' => Str::plural(__('submission'), $countOutdated) ]) }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="radio" class="form-check-input" name="rerunInclude" id="rerunIncludeMissing" value="missing"/>
                                    <label for="rerunIncludeMissing" class="form-check-label">{{ __('Include only the :count :word with missing tests', ['count' => $countMissing, 'word' => Str::plural(__('submission'), $countMissing) ]) }}</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" name="skipFullMarks" id="skipFullMarks" value="1"/>
                                    <label for="skipFullMarks" class="form-check-label">{{ __('Exclude the :count :word with full marks', ['count' => $countFullMarks, 'word'=> Str::plural(__('submission'), $countFullMarks)]) }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input af-check-enable-button" id="confirmRerun"
                                           data-af-button="#btnSubmitRerun"/>
                                    <label for="confirmRerun" class="form-check-label">{{ __('Are you sure?') }}</label>
                                </div>
                                <div class="text-center">
                                    <input class="btn btn-warning" type="submit" value="{{ __('Rerun') }}" id="btnSubmitRerun" disabled>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
