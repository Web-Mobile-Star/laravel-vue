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
@php
/**
 * @var \App\JUnit\JUnitTestSuite $suite
 * @var string $idPrefix
 * @var boolean $missing
 * @var boolean $showClassRows
 */
@endphp
<div class="card">
    <div class="card-header card-title" data-toggle="collapse" data-target="#{{ $idPrefix }}" aria-expanded="{{ $suite->isPassed() ? 'false' : 'true' }}" aria-controls="{{ $idPrefix }}">
        @if($missing)
            <span class="fa fa-question-circle"></span>
        @elseif($suite->isPassed())
            <span class="fa fa-check-circle"></span>
        @else
            @if($suite->countErrors)<span class="fa fa-exclamation-triangle"></span>@endif
            @if($suite->countFailures)<span class="fa fa-times-circle"></span>@endif
        @endif
        {{ $suite->name }}
    </div>
    <div id="{{ $idPrefix }}" class="card-body collapse @unless($suite->isPassed()) show @endif p-0" style="table-layout: fixed">
      <table class="table m-0" style="table-layout: fixed">
        <colgroup>
          <col width="40%"/>
          <col width="60%"/>
        </colgroup>
        <tbody>
        @foreach($suite->testCases as $tc)
            @if($showClassRows && ($loop->index == 0 || $suite->testCases[$loop->index - 1]->className != $tc->className))
                <tr><th scope="row" colspan="2">{{ $tc->className }}</th></tr>
            @endif
            @include('assessments._showAssessmentTest', ['tc' => $tc, 'atest' => $submission->assessment->testFor($tc)])
        @endforeach
        </tbody>
      </table>
    </div>
</div>
