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

    /**
     * @var string $fieldName
     * @var string $accept
     * @var bool $required
     * @var string $promptText
     */
@endphp
<div class="form-group row">
    <div class="custom-file">
        <input type="file" class="custom-file-input @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}"
               name="{{ $fieldName }}"
               accept="{{ $accept  }}" @if($required) required @endif>
        <label class="custom-file-label" id="{{ $fieldName . 'Label' }}" for="{{ $fieldName }}">{{ __($promptText) }}</label>
        @error($fieldName)
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div>
@push('scripts')
    <script type="application/javascript">
        $('{{ '#' . $fieldName }}').change(function (e) {
            var fileName = e.target.files[0].name;
            $('{{ '#' . $fieldName . 'Label' }}').html(fileName);
        });
    </script>
@endpush
