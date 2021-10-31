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

use App\TeachingModule;
use App\TeachingModuleItem;
use Illuminate\View\Component;

class ItemPath extends Component
{
    /** @var TeachingModule $module */
    public $module;
    /** @var TeachingModuleItem $item */
    public $item;

    /**
     * Create a new item path component.
     *
     * @param TeachingModule $module
     * @param TeachingModuleItem|null $item
     */
    public function __construct(TeachingModule $module, ?TeachingModuleItem $item)
    {
        $this->module = $module;
        $this->item = $item;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.item-path');
    }
}
