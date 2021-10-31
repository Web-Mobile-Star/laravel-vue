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

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class FileUploadField extends Component
{
    /**
     * @var string
     */
    public $fieldName;

    /**
     * @var string
     */
    public $accept;

    /**
     * @var bool
     */
    public $required;

    /**
     * @var string
     */
    public $promptText;

    /**
     * Create a new file upload field.
     *
     * @param string $fieldName  Name and ID for the <input>.
     * @param string $accept     Comma-separated list of MIME types and/or extensions (e.g. "text/csv,.csv").
     * @param bool $required     Is the file required?
     * @param string $promptText Prompt text for the field.
     */
    public function __construct(string $fieldName, string $accept, bool $required=true, string $promptText='')
    {
        $this->fieldName = $fieldName;
        $this->accept = $accept;
        $this->required = $required;
        $this->promptText = $promptText;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View
     */
    public function render()
    {
        return view('components.file-upload-field');
    }
}
