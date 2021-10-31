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
     * @var App\AssessmentSubmission $submission
     * @var \App\JUnit\JUnitTestCase $tc
     * @var ?\App\AssessmentTest $atest
     */
@endphp
<tr class="af-suite-tests-table table">
    @if($tc->skipped)
        <th scope="row" class="bg-light">{{ $tc->name }} <span class="fa fa-ban"/></th>
    @elseif($tc->failure)
        <th scope="row" class="bg-danger">{{ $tc->name }} <span class="fa fa-times-circle"/></th>
    @elseif($tc->error)
        <th scope="row" class="bg-warning">{{ $tc->name }} <span class="fa fa-exclamation-triangle"/></th>
    @elseif($tc->missing)
        <th scope="row" class="bg-dark text-light">{{ $tc->name }} <span class="fa fa-question-circle"/></th>
    @else
        <th scope="row" class="bg-success">{{ $tc->name }} <span class="fa fa-check-circle"/></th>
    @endif
    <td>
        <p>{{ $tc->status() }}@if($atest):
            @if($tc->isPassed()){{ $atest->points }} @else 0.00 @endif / {{ $atest->points }}
            @else: not in the model solution @endif</p>
        @if($tc->failure && $tc->failure->text)
            <h5>{{ __('Failure message') }}</h5>
            <pre class="pre-scrollable bg-dark text-light p-1">{{ $tc->failure->text }}</pre>
        @elseif($tc->error && $tc->error->text)
            <h5>{{ __('Error message') }}</h5>
            <pre class="pre-scrollable bg-dark text-light p-1">{{ $tc->error->text }}</pre>
        @endif
        @if($tc->stdout)
            <h5>{{ __('Normal Output') }}</h5>
            <pre class="pre-scrollable bg-dark text-light p-1">{{ $tc->stdout }}</pre>
        @endif
        @if($tc->stderr)
            <h5>{{ __('Error Output') }}</h5>
            <pre class="pre-scrollable bg-dark text-light p-1">{{ $tc->stderr }}xyz</pre>
        @endif
        @if($atest && $atest->feedback_markdown)
            @inject('tm', '\App\Markdown\TestMarkdownService')
            <h5>{{ __('Feedback') }}</h5>
            <div class="border p-1">{!! $tm->render($tc, $atest->feedback_markdown) !!}</div>
        @endif
    </td>
</tr>

