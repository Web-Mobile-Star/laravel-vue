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

@extends('layouts.base')

@section('title', __('Help - Markdown Conditionals'))

@section('main')
    <main class="py-4">
        <div class="container">
            <h1>{{ __('AutoFeedback conditional Markdown reference') }}</h1>
            <p>{{ __('The system supports a number of Markdown extensions to show certain feedback text only under specific conditions.') }}</p>
            <h2>{{ __('Available options') }}</h2>
            <ul>
                <li>
                    <p><b>af_hide</b>: {{ __('hides all content inside. Used as a quick way to "comment out" some text temporarily. For example:') }}</p>
                    @markdown
                    ````
                    ```af_hide
                    Will never show.
                    ```
                    ````
                    @endmarkdown
                </li>
                <li>
                    <p><b>af_when_passed</b>: {{ __('will only show if the test has passed. For example:') }}</p>
                    @markdown
                    ````
                    ```af_when_passed
                    Will only show if the test passed.
                    ```
                    ````
                    @endmarkdown
                </li>
                <li>
                    <p><b>af_when_failed</b>: {{ __('will only show if the test has failed. For example:') }}</p>
                    @markdown
                    ````
                    ```af_when_failed
                    Will only show if the test failed.
                    ```
                    ````
                    @endmarkdown
                </li>
                <li>
                    <p><b>af_when_substring</b>: {{ __('will only show if the test output contains this substring. For example:') }}</p>
                    @markdown
                    ````
                    ```af_when_substring MY SUBSTRING
                    Will only show if the test output contained "MY SUBSTRING".
                    ```
                    ````
                    @endmarkdown
                </li>
                <li>
                    <p><b>af_when_regex</b>: {!! __('will only show if the test output contains a match for the specified regular expression (according to :link). For example:', ['link' => '<a href="https://www.php.net/manual/en/function.preg-match">preg_match</a>'])  !!}</p>
                    @markdown
                    ````
                    ```af_when_regex /myregex/
                    Will only show if the test output had a match for the /myregex/ regular expression.
                    ```
                    ````
                    @endmarkdown
                    <p>{!! __('Note that it is possible to do case-insensitive matching with this (e.g. using <code>/myregex/i</code>).') !!}</p>
                </li>
            </ul>
            <h2>{{ __('Nesting blocks') }}</h2>
            <p>{{ __('It is possible to nest blocks inside other blocks (e.g. to have code snippets inside conditional blocks), by having the outer blocks start with more backticks.') }}</p>
            <p>{{ __('For instance, in this example we use 4 backticks for the outer block, and 3 backticks for the inner block:') }}</p>
            <pre><code class="language-markdown hljs hljs-string markdown">````af_when_failed
This is some text.
```java
System.out.println("Some text");
```
````</code></pre>
            <p>{{ __('The Markdown preview will show how the various blocks nest, and when they will appear.') }}</p>
        </div>
    </main>
@endsection
