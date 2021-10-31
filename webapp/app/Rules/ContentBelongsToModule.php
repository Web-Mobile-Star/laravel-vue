<?php

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

namespace App\Rules;

use App\Folder;
use App\TeachingModule;
use Illuminate\Contracts\Validation\Rule;

class ContentBelongsToModule implements Rule
{
    /**
     * @var TeachingModule
     */
    private $module;

    /**
     * Create a new rule instance.
     *
     * @param TeachingModule $module
     */
    public function __construct(TeachingModule $module)
    {
        $this->module = $module;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        /** @var Folder $folder */
        $folder = Folder::findOrFail($value);
        return $folder->usage->teaching_module_id === $this->module->id;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The content must belong to the module.');
    }
}
