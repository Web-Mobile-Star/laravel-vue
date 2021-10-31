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

    /**
     * @var App\TeachingModule $module
     * @var ?App\AssessmentSubmission $submission
     * @var int $keepLatest
     */
@endphp
@if($submission)
    @can('rerun', $submission)
        <h3 class="mt-4 text-center">{{ __('Rerun submission') }}</h3>
        <form
            action="{{ route('modules.submissions.rerun', ['module' => $module->id, 'submission' => $submission->id ]) }}"
            method="POST">
            @csrf
            <div class="text-center mt-2">
                <p>
                    @if($submission->updated_at->isBefore($submission->assessment->latestModelSolution->updated_at))
                        {{ __('Your submission is older than the latest model solution: it is recommended to rerun your submission against the latest tests.') }}
                    @else
                        {{ __('This will rerun this attempt on the latest model solution.') }}
                    @endif
                </p>
                @include('assessments._explainKeepLatest', ['keepLatest' => $keepLatest, 'assessment' => $submission->assessment])
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input af-check-enable-button" id="confirmRerun"
                           data-af-button="#btnSubmitRerun"/>
                    <label for="confirmRerun" class="form-check-label">{{ __('Are you sure?') }}</label>
                </div>
                <input class="btn btn-warning" type="submit" value="{{ __('Rerun') }}" id="btnSubmitRerun" disabled>
            </div>
        </form>
    @endcan
@endif
