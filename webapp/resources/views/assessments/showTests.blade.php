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
                <li class="breadcrumb-item active">Tests</li>
            </x-item-path>

            @if(count($tests) > 0)
                @can('modifyTests', $assessment)
                <a href="{{ route('modules.assessments.editTests', ['module' => $module->id, 'assessment' => $assessment->id]) }}" class="btn btn-primary mr-2">{{ __('Edit') }}</a>
                @endcan

                @foreach($tests as $test)
                    <div class="mt-4">
                        <h4>{{ $test->class_name }}::{{ $test->name }}</h4>
                        @if($test->task)
                            <div class="row">
                                <div class="col-sm-1 font-weight-bold">{{ __('Task') }}</div>
                                <div class="col-sm-8 ">{{ $test->task }}</div>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-sm-1 font-weight-bold">{{ __('Points') }}</div>
                            <div class="col-sm-2 ">{{ $test->points }}</div>
                        </div>
                        <div>
                            <div class="font-weight-bold">{{ __('Feedback template') }}</div>
                            <div class="border p-2">@markdown($test->feedback_markdown)</div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="alert alert-warning mt-2" role="alert">
                    {{ __('No model solution has been set up yet, or the model solution has no tests.') }}
                </div>
            @endif
        </div>
    </div>
@endsection
