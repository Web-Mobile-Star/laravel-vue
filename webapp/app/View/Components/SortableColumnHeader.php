<?php

/**
 *  Copyright 2021 Aston University
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

class SortableColumnHeader extends Component
{
    /**
     * @var string Human-readable text to show as the column header.
     */
    public $text;

    /**
     * @var string Key to provide as the sortKey parameter (e.g. 'id' or 'marks') for this column.
     */
    public $sortKey;

    /**
     * @var string Key of the currently active sort key (which may or may not be this header's sort key).
     */
    public $activeSortKey;

    /**
     * @var string Sorting order (should be either 'asc' or 'desc') to apply when link is clicked.
     */
    public $sortOrder;

    /**
     * @var callable
     */
    public $urlGenerator;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(string $sortKey, string $sortOrder, string $activeSortKey, callable $urlGenerator)
    {
        $this->sortKey = $sortKey;
        $this->sortOrder = $sortOrder;
        $this->activeSortKey = $activeSortKey;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|string
     */
    public function render()
    {
        return view('components.sortable-column-header');
    }
}
