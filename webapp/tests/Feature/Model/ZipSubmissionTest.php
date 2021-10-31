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

namespace Tests\Feature\Model;

use App\ZipSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use App\Zip\ExtendedZipArchive;
use Tests\TestCase;
use ZipArchive;

class ZipSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function testListFiles() {
        Storage::fake('local');

        $zipPath = 'test-resources/java-policy.zip';
        ExtendedZipArchive::zipTree('test-resources/java-policy', $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $path = Storage::putFile('submissions', new File($zipPath));
        /** @var ZipSubmission $submission */
        $submission = ZipSubmission::factory()->create([
            'diskPath' => $path,
        ]);

        $paths = $submission->getFilePathsInZIP();
        $this->assertContains('pom.xml', $paths);
        $this->assertContains('src/test/java/uk/ac/aston/autofeedback/policy/BadBehaviourTest.java', $paths);
        $this->assertNotContains('src/main/java/', $paths);
    }
}
