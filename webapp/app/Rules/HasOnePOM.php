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
use ZipArchive;

/**
 * Checks that the attribute is a ZIP file that has exactly one Maven POM
 * inside it.
 *
 * Assumes that the user already checked it is a ZIP file, and that it is
 * not too large.
 */
class HasOnePOM implements Rule
{
    const POM_FILENAME = 'pom.xml';

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $zip = new ZipArchive();
        if ($zip->open($value) === TRUE) {
            $pomCount = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (basename($stat['name']) == self::POM_FILENAME) {
                    $pomCount++;
                }
            }
            $zip->close();
            return $pomCount == 1;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The file does not contain exactly one ":pomFilename" file',
            ['pomFilename' => self::POM_FILENAME]);
    }
}
