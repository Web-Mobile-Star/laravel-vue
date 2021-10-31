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

use App\TeachingModuleItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class TeachingModuleItemList extends Component
{
    /**
     * @var Collection with the {@link TeachingModuleItem}s to be shown.
     */
    public $items;

    /**
     * Create a new component instance.
     *
     * @param Collection $items List of {@link TeachingModuleItem} to be shown.
     */
    public function __construct(Collection $items)
    {
        $this->items = $items;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.teaching-module-item-list');
    }
}
