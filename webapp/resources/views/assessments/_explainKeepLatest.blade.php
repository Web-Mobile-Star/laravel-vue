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

    /**
     * @var App\Assessment $assessment
     * @var int $keepLatest
     */
@endphp
<p>
    @if( $keepLatest == 1 && $assessment->due_by)
        {{ __('The system keeps the most recent attempt, as well as the best attempt overall and the best attempt before the deadline.') }}
    @elseif( $keepLatest == 1 )
        {{ __('The system keeps the most recent attempt, as well as the best attempt overall.') }}
    @elseif($keepLatest > 1 && $assessment->due_by)
        {{ __('The system keeps the most recent :attempts attempts, as well as the best attempt overall and the best attempt before the deadline.', ['attempts' => $keepLatest]) }}
    @elseif($keepLatest > 1)
        {{ __('The system keeps the most recent :attempts attempts, as well as the best attempt overall.', ['attempts' => $keepLatest]) }}
    @else
        {{ __('The system keeps all previous attempts.') }}
    @endif
</p>
