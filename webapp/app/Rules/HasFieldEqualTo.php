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
use Illuminate\Support\Facades\DB;

class HasFieldEqualTo implements Rule
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $field;

    /**
     * @var mixed
     */
    private $fieldValue;

    /**
     * @var string|null
     */
    private $message;

    /**
     * Create a new rule instance.
     *
     * @param string $table
     * @param string $field
     * @param mixed $fieldValue
     * @param string|null $message Custom message to be reported to the user.
     */
    public function __construct(string $table, string $field, $fieldValue, string $message = null)
    {
        $this->table = $table;
        $this->field = $field;
        $this->fieldValue = $fieldValue;
        $this->message = $message;
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
        $obtained = DB::table($this->table)
            ->select($this->field)
            ->where('id', $value)
            ->value($this->field);

        return $obtained == $this->fieldValue;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if (is_null($this->message)) {
            return __('The related field does not have the expected value.');
        } else {
            return __($this->message);
        }
    }
}
