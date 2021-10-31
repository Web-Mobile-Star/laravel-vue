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

use App\ModelSolution;
use Illuminate\View\Component;

class ModelSolutionVersionDescription extends Component
{
    /**
     * @var ModelSolution
     */
    public $modelSolution;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(ModelSolution $modelSolution)
    {
        $this->modelSolution = $modelSolution;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.model-solution-version-description');
    }
}
