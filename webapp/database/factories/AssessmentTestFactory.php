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
use App\AssessmentTest;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentTestFactory extends Factory {
    protected $model = AssessmentTest::class;

    public function definition()
    {
        return [
            'class_name' => $this->faker->name,
            'name' => $this->faker->name,
            'points' => $this->faker->randomFloat(2, 0, 100),
            'feedback_markdown' => $this->faker->text,
            'assessment_id' => Assessment::factory(),
        ];
    }
}
