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

use Illuminate\Contracts\Validation\Rule;
use Spatie\Permission\Traits\HasRoles;

class ModelHasRoleID implements Rule
{
    /**
     * @var \App\TeachingModuleUser|null
     */
    private $model;

    /**
     * Create a new rule instance.
     *
     * @param HasRoles|null $model
     */
    public function __construct($model)
    {
        $this->model = $model;
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
        if (is_null($this->model)) {
            return false;
        } else {
            $roleIDs = $this->model->roles()->pluck('id')->toArray();
            return in_array($value, $roleIDs);
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if (is_null($this->model)) {
            return __('The provided module user does not exist.');
        } else {
            return __('The model must have the provided role.');
        }
    }
}
