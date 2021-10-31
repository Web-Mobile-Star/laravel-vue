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

use App\TeachingModule;
use App\TeachingModuleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeachingModuleItemFactory extends Factory {
    protected $model = TeachingModuleItem::class;

    public function definition()
    {
        return [
            'teaching_module_id' => TeachingModule::factory(),
            // folder is not faked - up to user
            'title' => $this->faker->sentence,
            'description_markdown' => $this->faker->text,
            'available' => true,
            'available_from' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'available_until' => $this->faker->dateTimeBetween('now', '+1 year'),
            // content is not faked - up to user
        ];
    }

}
