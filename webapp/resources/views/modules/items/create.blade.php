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
@endphp

@extends('layouts.app')

@php /** @var \App\TeachingModuleItem $item */ @endphp

@section('title')
    @if($item->exists){{ __('Edit Item') }}@else{{ __('Create Item')}}@endisset
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if($item->exists)
                <x-item-path :module="$module" :item="$item">
                    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
                </x-item-path>
            @else
                <x-item-path :module="$module" :item="$container">
                    <li class="breadcrumb-item active">{{ __($itemType === 'folder' ? 'Create Folder' : ($itemType === 'assessment' ? 'Create Assessment' : 'Create Item')) }}</li>
                </x-item-path>
            @endif

            <form method="POST"
                  @if($item->exists) action="{{ route('modules.items.update', ['module' => $module->id, 'item' => $item->id]) }}"
                  @else action="{{ route('modules.items.store', $module->id) }}" @endisset>
                @csrf
                @if($item->exists)
                    @method('PUT')
                @else
                    @if(isset($itemType) && ($itemType === 'folder' || $itemType === "assessment" ))
                        <input type="hidden" name="itemType" value="{{ $itemType }}"/>
                    @endif
                    @if(isset($folder) && $folder)
                        <input type="hidden" name="itemFolder" value="{{ $folder }}" />
                    @endif
                @endif

                <div class="form-group row">
                    <label for="itemTitle">{{ __('Title') }}</label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" id="itemTitle"
                           name="itemTitle" required aria-required="true"
                           value="{{ old('itemTitle', $item->title) }}"/>
                    @error('itemTitle')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>

                <div class="form-group row">
                    <label for="itemDescription">{{ __('Markdown description') }} (<x-markdown-help-links/>)</label>
                    <textarea class="form-control @error('itemDescription') is-invalid @endif" id="itemDescription"
                              rows="10" required aria-required="true"
                              name="itemDescription">{{ old('itemDescription', $item->description_markdown) }}</textarea>
                    @error('itemDescription')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>

                <div class="form-group row">
                    <div class="form-check">
                        <input class="form-check-input @error('itemAvailable') is-invalid @endif" type="checkbox"
                               id="itemAvailable" name="itemAvailable"
                               @if(old('itemAvailable', $item->available)) checked @endif />
                        <label class="form-check-label"
                               for="itemAvailable">{{ __('Make available to students?') }}</label>
                        @error('itemAvailable')
                        <div class="invalid-feedback">
                            <strong>{{ $message }}</strong>
                        </div>
                        @enderror
                    </div>
                </div>

                <div class="form-group row form-inline">
                    <label for="itemAvailableStart" class="col-sm-3 text-left">{{ __('Available from') }}</label>
                    <div class="input-append date form_datetime col-sm-6">
                        <span class="add-on">
                            <div class="input-group @error('itemAvailableStart') is-invalid @endif">
                                <input size="16" class="form-control @error('itemAvailableStart') is-invalid @endif"
                                       type="text" name="itemAvailableStart"
                                       value="{{ old('itemAvailableStart', $item->available_from ? $item->available_from->format($dateTimeFormat) : '') }}"
                                       readonly/>
                                <div class="input-group-append">
                                    <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                </div>
                            </div>
                            @error('itemAvailableStart')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </span>
                    </div>
                </div>

                <div class="form-group row form-inline">
                    <label for="itemAvailableEnd" class="col-sm-3">{{ __('Available until') }}</label>
                    <div class="input-append date form_datetime col-sm-6">
                        <span class="add-on">
                            <div class="input-group @error('itemAvailableEnd') is-invalid @endif">
                                <input size="16" class="form-control @error('itemAvailableEnd') is-invalid @endif"
                                       type="text" name="itemAvailableEnd"
                                       value="{{ old('itemAvailableEnd', $item->available_until ? $item->available_until->format($dateTimeFormat) : '') }}"
                                       readonly/>
                                <div class="input-group-append">
                                    <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                </div>
                            </div>
                            @error('itemAvailableEnd')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </span>
                    </div>
                </div>

                @if($itemType == 'assessment')
                    @include('modules.items._createEditAssessment')
                @endif

                <div class="text-center">
                    <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                    <a role="button" class="btn btn-secondary"
                       href="{{ route('modules.show', $module->id) }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('extrajs')
    <script>
        $('.form_datetime').datetimepicker({
            format: "dd/mm/yyyy hh:ii",
            weekStart: 1,
            clearBtn: true,
            autoclose: true,
            todayBtn: true,
            pickerPosition: "bottom-left",
        });
    </script>
@endsection
