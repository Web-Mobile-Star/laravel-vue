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

use App\ZipSubmission;
use Illuminate\View\Component;

class JobStatusCell extends Component
{
    /**
     * @var ZipSubmission
     */
    public $job;

    /**
     * @var bool If true, the page will reload once the job finishes (aborted, completed, or failed).
     */
    public $reloadOnFinish;

    /**
     * Create a new component instance.
     *
     * @param ZipSubmission $job
     * @param bool $reloadOnFinish
     */
    public function __construct(ZipSubmission $job, bool $reloadOnFinish = false)
    {
        $this->job = $job;
        $this->reloadOnFinish = $reloadOnFinish;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.job-status-cell');
    }
}
