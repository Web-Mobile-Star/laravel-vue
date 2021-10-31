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

@php /** @var \App\TeachingModule $module */ @endphp
@php /** @var \App\TeachingModuleItem $item */ @endphp

@section('title'){{ $item->title }}@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-item-path :module="$module" :item="$item"/>

            @if ($item->content_type === 'App\Folder')
                @include('modules.items.showFolder', ['folder' => $item->content, 'children' => $item->content->childrenVisibleByUser()])
            @elseif ($item->content_type === 'App\Assessment')
                @include('modules.items.showAssessment', ['assessment' => $item->content])
            @endif
        </div>
    </div>
@endsection
