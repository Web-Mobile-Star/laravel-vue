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

class ModalConfirm extends Component
{
    /** @var string $id HTML identifier for use by Bootstrap (to be used in data-target). */
    public $id;

    /** @var string $title Title for the modal dialog. */
    public $title;

    /**
     * Create a new component instance.
     *
     * @param string $id
     * @param string $title
     */
    public function __construct(string $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|string
     */
    public function render()
    {
        return view('components.modal-confirm');
    }
}
