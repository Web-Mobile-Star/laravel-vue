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
     */
@endphp
<h3 class="mt-4 text-center">{{ __('Assessment details') }}</h3>
<div class="form-group row form-inline">
    <label for="dueBy" class="col-sm-3">{{ __('Submission due by') }}</label>
    <div class="input-append date form_datetime col-sm-6">
        <span class="add-on">
            <div class="input-group @error('dueBy') is-invalid @endif">
                <input size="16" class="form-control @error('dueBy') is-invalid @endif"
                    type="text" name="dueBy"
                    value="{{ old('dueBy', $item->content && $item->content->due_by ? $item->content->due_by->format($dateTimeFormat) : '') }}"
                    readonly />
                <div class="input-group-append">
                    <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                </div>
            </div>
            @error('dueBy')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </span>
    </div>
</div>
