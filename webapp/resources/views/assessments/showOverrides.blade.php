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

@section('title', __('File overrides - :title', ['title' => $assessment->usage->title]))

@php
/**
 * @var \App\TeachingModule $module
 * @var \App\Assessment $assessment
 * @var \Illuminate\Database\Eloquent\Collection $overrides
 */
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">
            <x-item-path :module="$module" :item="$assessment->usage">
                <li class="breadcrumb-item active">{{ __('File overrides') }}</li>
            </x-item-path>

            @if($assessment->latestModelSolution)
                @if(count($selections) > 0)
                    @if($canModify)
                        <form method="POST" action="{{ route('modules.assessments.storeOverrides', ['module' => $module->id, 'assessment' => $assessment->id]) }}" id="overridesForm">
                            @csrf
                            @endif
                            <table class="table table-hover mt-4 af-row-select-table" id="selectionsTable">
                                <thead>
                                @if($canModify)
                                    <th scope="col"><input type="checkbox" class="af-row-select-all"/></th>
                                @else
                                    <th scope="col"></th>
                                @endif
                                <th scope="col">Path</th>
                                </thead>
                                <tbody>
                                @foreach ($selections as $path => $selected)
                                    <tr>
                                        <td><input type="checkbox" class="af-row-select" name="paths[]" value="{{ $path }}" @if($selected) checked @endif @unless($canModify) disabled @endif /></td>
                                        <td>{{ $path }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @if($canModify)
                                <input type="submit" class="btn btn-primary" value="{{ __('Update') }}"/>
                        </form>
                    @endif
                @else
                    <div class="alert alert-warning mt-2" role="alert">
                        {{ __('No files have been found in the uploaded ZIP.') }}
                    </div>
                @endif
            @else
                <div class="alert alert-warning mt-2" role="alert">
                    {{ __('No model solution has been provided yet.') }}
                </div>
            @endif
        </div>
    </div>
@endsection
