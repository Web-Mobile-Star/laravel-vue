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

@section('title', __('Tests - :title', ['title' => $assessment->usage->title]))

@php
    /**
     * @var \App\TeachingModule $module
     * @var \App\Assessment $assessment
     * @var \Illuminate\Database\Eloquent\Collection $tests
     */
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module" :item="$assessment->usage">
                <li class="breadcrumb-item"><a
                        href="{{ route('modules.assessments.showTests', ['module' => $module->id, 'assessment' => $assessment->id ]) }}">{{ __('Tests') }}</a>
                </li>
                <li class="breadcrumb-item active">{{ __('Edit') }}</li>
            </x-item-path>

            @if(count($tests) > 0)
                <form method="POST"
                      action="{{ route('modules.assessments.storeTests', ['module' => $module->id, 'assessment' => $assessment->id]) }}"
                      id="testsForm">
                    @csrf

                    @foreach($tests as $test)
                        <div class="mt-4 p-2">
                            <input type="hidden" name="id[]" value="{{ $test->id }}"/>
                            <h4>{{ $test->class_name }}::{{ $test->name }}</h4>
                            <div class="form-group row">
                                <label for="test{{ $test->id }}Task"
                                       class="col-sm-1 col-form-label">{{ __('Task') }}</label>
                                <input type="text" class="col-sm-8 @error('task.' . $loop->index) is-invalid @endif form-control"
                                       id="test{{ $test->id }}Task" name="tasks[]"
                                       placeholder="{{ __('Task group (optional)') }}"
                                       value="{{ old('tasks.' . $loop->index, $test->task) }}" />
                                @error('tasks.' . $loop->index)
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            <div class="form-group row">
                                <label for="test{{ $test->id }}Points"
                                       class="col-sm-1 col-form-label">{{ __('Points') }}</label>
                                <input type="number" step="0.01"
                                       class="col-sm-2 @error('points.' . $loop->index) is-invalid @endif form-control"
                                       id="test{{ $test->id }}Points" name="points[]"
                                       value="{{ old('points.' . $loop->index, $test->points) }}" min="0"/>
                                @error('points.' . $loop->index)
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="test{{ $test->id }}Feedback">{{ __('Feedback template') }} (
                                    Markdown:
                                    <x-markdown-help-links :supportsConditional="true" />
                                    )</label>
                                <textarea class="@error('feedback.' . $loop->index) is-invalid @endif form-control"
                                          id="test{{ $test->id }}Feedback" rows="5"
                                          name="feedback[]">{{ old('feedback.' . $loop->index, $test->feedback_markdown ) }}</textarea>
                                @error('feedback.' . $loop->index)
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <input type="submit" class="btn btn-primary" value="{{ __('Update') }}"/>
                </form>
            @else
                <div class="alert alert-warning mt-2" role="alert">
                    {{ __('No model solution has been set up yet, or the model solution has no tests.') }}
                </div>
            @endif
        </div>
    </div>
@endsection
