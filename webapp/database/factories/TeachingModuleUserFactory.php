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

use App\TeachingModuleUser;
use App\TeachingModule;
use App\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeachingModuleUserFactory extends Factory
{
    protected $model = TeachingModuleUser::class;

    public function definition()
    {
        return [
            'teaching_module_id' => TeachingModule::factory(),
            'user_id' => User::factory(),
        ];
    }
}
