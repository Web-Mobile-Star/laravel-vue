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
<a href="https://commonmark.org/help/" target="_blank">{{ __('syntax') }}</a>
+ <a target="_blank" href="https://github.com/spatie/commonmark-highlighter#highlighting-specific-lines">{{ __('code higlighting')  }}</a>
@if($supportsConditional)
+ <a target="_blank" href="{{ route('help.markdown.conditionals') }}">{{ __('conditional blocks') }}</a>
@endif
