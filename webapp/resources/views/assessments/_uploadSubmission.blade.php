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

    /**
     * @var App\TeachingModuleItem $item
     * @var App\TeachingModule $module
     * @var App\Assessment $assessment
     * @var ?App\AssessmentSubmission $submission
     * @var int $keepLatest
     */
@endphp
<h3 class="mt-4 text-center">{{ __($submission ? 'Replace submission' : 'Upload submission') }}</h3>
<form
    action="{{ route('modules.assessments.storeSubmission', ['module' => $module->id, 'assessment' => $assessment->id ]) }}"
    method="POST" enctype="multipart/form-data">
    @csrf
    <x-file-upload-field :fieldName="'jobfile'" :required="true" :promptText="'Choose ZIP file'"
                         :accept="'application/zip,.zip'"/>
    <div class="form-check">
        <input type="checkbox" class="form-check-input af-check-enable-button" id="confirmFeedbackIntent" name="feedbackIntentUnderstood" data-af-button="#btnSubmitSubmission" value="true" />
        <label for="confirmFeedbackIntent" class="form-check-label text-danger font-weight-bold">{{ __('I understand that this system is only for early feedback, and that I should upload the final ZIP to Blackboard.') }}</label>
    </div>
    <div class="text-center mt-2">
        @if($submission)
            <p>
                {{ __('This will add a new attempt to the submission.') }}
            </p>
            @include('assessments._explainKeepLatest', ['keepLatest' => $keepLatest, 'assessment' => $assessment])
        @endif
        <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}" id="btnSubmitSubmission" disabled>
    </div>
</form>
