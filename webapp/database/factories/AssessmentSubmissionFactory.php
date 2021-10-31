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
use App\TeachingModuleItem;
use App\TeachingModuleUser;
use App\ZipSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentSubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AssessmentSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'passed' => $this->faker->numberBetween(0, 10),
            'skipped' => $this->faker->numberBetween(0, 10),
            'errors' => $this->faker->numberBetween(0, 10),
            'failed' => $this->faker->numberBetween(0, 10),
            'missing' => $this->faker->numberBetween(0, 10),
            'points' => number_format($this->faker->randomFloat(2, 0, 100), 2),
            'attempt' => $this->faker->numberBetween(1, 5),
            'teaching_module_user_id' => TeachingModuleUser::factory(),
            'zip_submission_id' => function (array $attributes) {
                // The ZipSubmission user must match that of the TeachingModuleUser
                return ZipSubmission::factory([
                   'user_id' => TeachingModuleUser::find($attributes['teaching_module_user_id'])->user_id,
                ]);
            },
            'assessment_id' => Assessment::factory(),
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (AssessmentSubmission $sub) {
           if (is_null($sub->assessment->usage)) {
               /** @var TeachingModuleItem $tmi */
               $tmi = TeachingModuleItem::factory()->create([
                   'teaching_module_id' => $sub->author->teaching_module_id,
               ]);
               $sub->assessment->usage()->save($tmi);
               $sub->assessment->refresh();
           }
        })->afterCreating(function (AssessmentSubmission $sub) {
            if (is_null($sub->model_solution_id) && $sub->assessment->latestModelSolution) {
                $sub->model_solution_id = $sub->assessment->latestModelSolution->id;
                $sub->save();
            }
        });
    }
}
