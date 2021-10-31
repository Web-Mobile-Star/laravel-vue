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

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('modules.show', $module->id) }}">{{ $module->name }}</a></li>
        @if($item)
            @foreach($item->path() as $p)
                @if($loop->last && $slot->isEmpty())
                    <li class="breadcrumb-item active" aria-current="page">{{ $p->title }}</li>
                @elseif($p->exists)
                    @if ($p->content_id)
                        <li class="breadcrumb-item"><a
                                href="{{ route('modules.items.show', ['module' => $module->id, 'item' => $p->id]) }}">{{ $p->title }}</a>
                        </li>
                    @else
                        <li class="breadcrumb-item">{{ $p->title }}</li>
                    @endif
                @endif
            @endforeach
        @endif
        {{ $slot }}
    </ol>
</nav>
