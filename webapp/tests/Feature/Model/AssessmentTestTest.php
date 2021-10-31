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

use App\Assessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTestTest extends TestCase
{
    use RefreshDatabase;

    public function testRelationships()
    {
        /** @var Assessment $assessment */
        $assessment = Assessment::factory()->create();
        /** @var \App\AssessmentTest[] $test */
        $tests = \App\AssessmentTest::factory()->count(3)->create([
            'assessment_id' => $assessment->id
        ]);

        $this->assertEquals($assessment->id, $tests[0]->assessment->id);
        $this->assertEquals(3, $assessment->tests()->count());
    }
}
