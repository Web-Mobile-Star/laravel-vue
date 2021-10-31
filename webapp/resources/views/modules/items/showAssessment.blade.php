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
     */
@endphp
<div class="btn-toolbar" role="toolbar" aria-label="{{ __('Folder items toolbar') }}">
    @can('viewModelSolution', $assessment)
        <a href="{{ route('modules.assessments.showModelSolution', ['module' => $module->id, 'assessment' => $assessment->id ]) }}"
           class="btn btn-primary mr-2">{{ __('Model solution') }}</a>
    @endcan
    @can('viewOverrides', $assessment)
        <a href="{{ route('modules.assessments.showOverrides', ['module' => $module->id, 'assessment' => $assessment->id]) }}"
           class="btn btn-primary mr-2">{{ __('File overrides') }}</a>
    @endcan
    @can('viewTests', $assessment)
        <a href="{{ route('modules.assessments.showTests', ['module' => $module->id, 'assessment' => $assessment->id]) }}"
           class="btn btn-primary mr-2">{{ __('Tests') }}</a>
    @endcan
    @can('viewProgress', $assessment)
        <a href="{{ route('modules.assessments.viewProgressChart', ['module' => $module->id, 'assessment' => $assessment->id]) }}"
           class="btn btn-primary mr-2" target="_blank">{{ __('Progress') }}</a>
    @endcan
    @can('viewSubmissions', $assessment)
       <a href="{{ route('modules.assessments.showSubmissions', ['module' => $module->id, 'assessment' => $assessment->id]) }}"
          class="btn btn-primary" target="_blank">{{ __('Submissions') }}</a>
    @endcan
</div>
@if($assessment->latestModelSolution)
    @php
        /**
         * @var App\TeachingModule $module
         * @var App\Assessment $assessment
         */
         $submission = $assessment->latestSubmissionFor(Auth::user()->moduleUser($module))->first();
    @endphp
    @include('assessments._showSubmission', [ 'submission' => $submission, 'byClass' => false ])
    @include('assessments._uploadSubmission', [ 'submission' => $submission, 'keepLatest' => Config::get(\App\Assessment::CONFIG_KEEP_LATEST_ATTEMPTS) ])
    @include('assessments._rerunSubmission', [ 'submission' => $submission, 'keepLatest' => Config::get(\App\Assessment::CONFIG_KEEP_LATEST_ATTEMPTS) ])
    @include('assessments._deleteSubmission', [ 'submission' => $submission ])
@else
    <div class="alert alert-warning mt-2"
         role="alert">{{ __('The assessment has not been set up yet. Please check back in a while.') }}</div>
@endif
