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

namespace Database\Factories;

use App\Assessment;
use App\AssessmentSubmission;
use App\ModelSolution;
use App\TeachingModuleItem;
use App\ZipSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory {
    protected $model = Assessment::class;

    public function definition()
    {
       return [
           // nothing for now
       ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Assessment $a) {
            if (is_null($a->latestModelSolution)) {
                ModelSolution::factory()->create([
                    'assessment_id' => $a->id,
                ]);
                $a->unsetRelation('latestModelSolution');
            }
        });
    }
}
