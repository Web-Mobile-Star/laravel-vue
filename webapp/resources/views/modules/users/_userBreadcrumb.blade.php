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
<div class="row justify-content-center">
    <div class="col-md-8">
        <x-item-path :module="$module">
            @can('viewUsers', $module)
                <li class="breadcrumb-item"><a href="{{ route('modules.users.index', ['module' => $module->id]) }}">{{ __('Users') }}</a></li>
            @else
                <li class="breadcrumb-item">{{ __('Users') }}</li>
            @endcan
            <li class="breadcrumb-item active">{{ $text }}</li>
        </x-item-path>
    </div>
</div>
