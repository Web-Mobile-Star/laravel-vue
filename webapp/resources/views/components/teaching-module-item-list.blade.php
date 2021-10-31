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
@if($items->count() > 0)
    <div class="list-group mt-2">
        @each('modules.items.listItem', $items, 'item')
    </div>
@else
    <div class="alert alert-warning mt-2" role="alert">{{ __('No module items are available yet.') }}</div>
@endif
