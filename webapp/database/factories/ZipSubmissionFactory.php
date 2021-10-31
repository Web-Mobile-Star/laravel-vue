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

use App\User;
use App\ZipSubmission;
use Faker\Generator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ZipSubmissionFactory extends Factory {
    protected $model = ZipSubmission::class;

    public function definition()
    {
        return [
            'filename' => $this->faker->name . '.zip',
            'diskPath' => 'submissions/' . $this->faker->sha1 . '.zip',
            'sha256' => $this->faker->sha256,
            'user_id' => User::factory(),
            'submitter_user_id' => function ($arr) {
                // By default, same as user ID
                return $arr['user_id'];
            },
            'status' => ZipSubmission::STATUS_OK
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (ZipSubmission $job) {
            if (!Storage::exists($job->diskPath)) {
                $faker = $this->withFaker();

                $fakeZipPath = tempnam(sys_get_temp_dir(), 'tmpzip');
                $fakeZip = new ZipArchive;
                if ($fakeZip->open($fakeZipPath, ZipArchive::CREATE)) {
                    // Add a few files into the ZIP
                    for ($i = 0; $i < 5; $i++) {
                        $fakeZip->addFromString($faker->name, $faker->text);
                    }
                    $fakeZip->close();
                }

                Storage::putFileAs(
                    dirname($job->diskPath),
                    new File($fakeZipPath),
                    basename($job->diskPath));

                unlink($fakeZipPath);
            }
        });
    }
}
