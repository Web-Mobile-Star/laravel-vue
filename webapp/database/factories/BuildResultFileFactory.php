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

use App\BuildResultFile;
use App\ZipSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BuildResultFileFactory extends Factory {
    protected $model = BuildResultFile::class;

    public function definition()
    {
        return [
            'zip_submission_id' => ZipSubmission::factory(),
            'source' => $this->faker->name,
            'gzipped' => function ($arr) {
                return BuildResultFile::isCompressible($arr['mimeType']);
            },
            'originalPath' => '/' . $this->faker->name . '.' . $this->faker->fileExtension,
            'mimeType' => $this->faker->mimeType,
        ];
    }

    public function configure() {
        return $this->afterMaking(function (BuildResultFile $rf) {
            $faker = $this->withFaker();

            $rf->diskPath = 'results/s' . $rf->submission->id . '/' . $faker->sha1;
            $contents = $faker->text;
            if ($rf->gzipped) {
                $rf->diskPath .= '.gz';
                $contents = gzencode($contents);
            } else {
                $ext = pathinfo($rf->originalPath, PATHINFO_EXTENSION);
                $rf->diskPath .= '.' . $ext;
            }

            Storage::putFileAs(
                dirname($rf->diskPath),
                UploadedFile::fake()->createWithContent(basename($rf->originalPath), $contents),
                basename($rf->diskPath));
        });
    }

}
