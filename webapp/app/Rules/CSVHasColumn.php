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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Exception;
use League\Csv\Reader;

class CSVHasColumn implements Rule
{
    /**
     * @var string Name of the column which should be in the CSV file.
     */
    private $columnName;

    /**
     * Create a new rule instance.
     *
     * @param string $columnName
     */
    public function __construct(string $columnName)
    {
        $this->columnName = $columnName;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  UploadedFile  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_object($value)) {
            return false;
        }

        $reader = Reader::createFromPath($value->getRealPath(), 'r');
        try {
            $reader->setHeaderOffset(0);
            $header = $reader->getHeader();
            return in_array($this->columnName, $header);
        } catch (Exception $e) {
            Log::error($e);
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The CSV file does not include the ":column" column.', ['column' => $this->columnName]);
    }
}
