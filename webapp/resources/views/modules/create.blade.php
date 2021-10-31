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

@section('title')
    @isset($module)
        Edit Module
    @else
        Create Module
    @endisset
@endsection

@section('content')
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center">
                    @isset($module)
                        Edit Module
                    @else
                        Create Module
                    @endisset
                </h2>

                <form method="POST" @isset($module) action="{{ route('modules.update', $module->id) }}"
                      @else action="{{ route('modules.store') }}" @endisset>
                    @csrf
                    @isset($module)
                        @method('PUT')
                    @endisset

                    <div class="form-group row">
                        <label for="moduleName">{{ __('Module name') }}</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="moduleName"
                               name="name" required aria-required="true"
                               @isset($module)value="{{ $module->name }}"@endisset/>
                        @error('name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>

                    <div class="text-center">
                        <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                        <a role="button" class="btn btn-secondary" href="{{ URL::previous() }}">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
