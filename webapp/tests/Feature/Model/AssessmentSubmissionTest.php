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

use App\AssessmentSubmission;
use App\ZipSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function testRelationships()
    {
        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();

        $this->assertNotNull($asub->assessment);
        $this->assertNotNull($asub->author);
        $this->assertNotNull($asub->submission);

        $this->assertContains($asub->id, $asub->assessment->submissions->pluck('id')->toArray());
        $this->assertContains($asub->id, $asub->author->submissions->pluck('id')->toArray());
        $this->assertEquals($asub->id, $asub->submission->assessment->id);

        $this->assertEquals(1, $asub->assessment->submissionsFor($asub->author)->count());
        $this->assertEquals(1, $asub->assessment->latestSubmissionFor($asub->author)->count());
        $this->assertEquals($asub->id, $asub->assessment->submissionsFor($asub->author)->first()->id);
    }

    public function testDelete() {
        /** @var AssessmentSubmission[] $asubs */
        $asubs = AssessmentSubmission::factory()->count(2)->create();
        $expectedDeleted = $asubs[0]->submission;

        $this->assertEquals(4, ZipSubmission::count());
        $asubs[0]->delete();
        $this->assertEquals(3, ZipSubmission::count());
        $this->assertDeleted($expectedDeleted);
    }

    public function testRerunOldAttempt() {
        Storage::fake();
        Queue::fake();

        /** @var AssessmentSubmission $asub */
        $asub = AssessmentSubmission::factory()->create();
        $a2 = $asub->rerun();
        $a3 = $asub->rerun();

        $this->assertEquals($asub->attempt + 1, $a2->attempt);
        $this->assertEquals($a2->attempt + 1, $a3->attempt);
    }
}
