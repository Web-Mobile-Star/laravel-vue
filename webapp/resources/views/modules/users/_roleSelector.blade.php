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
@php /** @var \App\TeachingModule $module */ @endphp
@php /** @var \App\TeachingModuleUser $moduleUser */ @endphp
@php /** @var \Spatie\Permission\Models\Role[] $roles */ @endphp

<div class="form-group row">
    <div class="col-sm-2">{{ __('Roles') }}</div>
    <div class="col-sm-10">
        @foreach($roles as $role)
            <div class="form-check">
                <input class="form-check-input @error('roles') is-invalid @enderror" type="checkbox"
                       id="role{{ $role->id }}"
                       value="{{ $role->id }}" name="roles[]"
                       @if(in_array($role->id, old('roles', [])))
                         checked
                       @elseif(is_null(old('roles')) && $moduleUser->hasRole($role->name))
                         checked
                       @endif
                />
                <label class="form-check-label" for="role{{ $role->id }}">
                    {{ \App\Policies\TeachingModuleUserPolicy::cleanRoleName($role->name) }}
                </label>
                @error('roles')
                @if ($loop->last)
                    <div class="invalid-feedback">
                        <strong>{{ $message }}</strong>
                    </div>
                @endif
                @enderror
            </div>
        @endforeach
    </div>
</div>
