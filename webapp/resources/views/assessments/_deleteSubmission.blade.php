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
     * @var App\TeachingModuleItem $item
     * @var App\TeachingModule $module
     * @var App\Assessment $assessment
     * @var ?App\AssessmentSubmission $submission
     */
@endphp
@if($submission)
    @can('delete', $submission)
        <h3 class="mt-4 text-center">{{ __('Delete submission') }}</h3>
        <form
            action="{{ route('modules.submissions.destroy', ['module' => $module->id, 'submission' => $submission->id ]) }}"
            method="POST">
            @csrf
            @method('DELETE')
            <div class="text-center mt-2">
                <p>
                    {{ __('Only this attempt will be deleted. Other attempts will not be affected.') }}
                </p>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input af-check-enable-button" id="confirmDelete"
                           data-af-button="#btnSubmitDelete"/>
                    <label for="confirmDelete" class="form-check-label">{{ __('Are you sure?') }}</label>
                </div>
                <input class="btn btn-danger" type="submit" value="{{ __('Delete') }}" id="btnSubmitDelete" disabled>
            </div>
        </form>
    @endcan
@endif
