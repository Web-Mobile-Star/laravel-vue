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
    {{ __('Enrol User') }}
@endsection

@section('content')
    @include('modules.users._userBreadcrumb', ['text' => __('Enrol User')])

    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST" action="{{ route('modules.users.store', $module->id) }}">
                @csrf

                <div class="form-group row">
                    <label class="col-sm-2 col-form-label" for="email">{{ __('Email') }}</label>
                    <div class="col-sm-10">
                        <input type="email" required aria-required="true"
                               class="af-autocomplete-email form-control @error('email') is-invalid @enderror"
                               id="email"
                               name="email"
                               placeholder="{{ __('email address (prefix autocomplete based on registered users)') }}"
                               value="{{ old('email') }}"
                        />
                        @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                </div>

                @include('modules.users._roleSelector')

                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                    <a role="button" class="btn btn-secondary"
                       href="{{ route('modules.users.index', $module->id) }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('extrajs')
    <script type="application/javascript">
        $('.af-autocomplete-email').autocomplete({
            delay: 300,
            source: (request, callback) => {
                axios.get("{{ route('modules.users.autocomplete', $module->id) }}", {
                    params: {prefix: request.term}
                }).then(function (response) {
                    callback(response.data);
                });
            }
        });
    </script>
@endsection
