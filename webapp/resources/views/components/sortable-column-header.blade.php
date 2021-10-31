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
@endphp
<th scope="col">
    <a href="{{ $urlGenerator($sortKey, $sortOrder == 'asc' && $activeSortKey == $sortKey ? 'desc' : 'asc') }}">
        {{ $slot }}
        @if ($activeSortKey == $sortKey)
            <i class="fas @if($sortOrder == 'asc') fa-sort-up @else fa-sort-down @endif"></i>
        @endif
    </a>
</th>
