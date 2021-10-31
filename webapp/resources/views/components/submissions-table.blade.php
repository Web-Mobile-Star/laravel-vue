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
 * @var \App\Http\Controllers\SubmissionsTable $table
 */
@endphp
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
        <tr>
            @foreach ($table->columns as $column)
                <x-sortable-column-header
                    :sort-key="$column[0]"
                    :sort-order="$table->columnOrder"
                    :active-sort-key="$table->columnSort"
                    :url-generator="$table->urlGenerator">{{ __($column[1]) }}</x-sortable-column-header>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($table->submissions as $s)
            <tr>
                <td><a href="{{ route('modules.submissions.show', ['module' => $module->id, 'submission' => $s->id]) }}">{{ $s->id }}</a></td>
                @if($table->showAssessment)
                <td><a href="{{ route('modules.items.show', ['module' => $module->id, 'item' => $s->assessment->usage->id ]) }}">{{ $s->assessment->usage->title }}</a></td>
                @endif
                @if($table->showAuthor)
                @can('view', $s->author)
                    <td><a href="{{ route('modules.users.show', ['module' => $module->id, 'user' => $s->author ]) }}">{{ $s->author->user->name }}</a></td>
                @else
                    <td>{{ $s->author->user->name }}</td>
                @endcan
                @endif
                <td>
                    {{ $s->created_at }}
                    @if($s->isLate())
                        <span class="badge badge-warning">{{ __('late') }}</span>
                    @endif
                </td>
                <td>#{{ $s->attempt }}</td>
                <td>@if($s->points == \App\AssessmentSubmission::POINTS_PENDING) {{ __('Pending') }} @else {{ $s->points }} @endif</td>
                <td>{{ $s->passed }}</td>
                <td>{{ $s->failed }}</td>
                <td>{{ $s->errors }}</td>
                <td>{{ $s->skipped }}</td>
                <td>{{ $s->missing }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $table->submissions->links() }}
