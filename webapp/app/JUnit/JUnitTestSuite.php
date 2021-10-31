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


namespace App\JUnit;


/**
 * Contains the results of a single JUnit test suite.
 */
class JUnitTestSuite
{
    /** @var string $name of the test suite. In Java, it'd be the fully qualified name of the class. */
    public $name;

    /** @var float $timeSeconds Wall clock time elapsed in seconds for the whole test suite. */
    public $timeSeconds;

    /** @var int $countTests Total number of tests in the test suite. */
    public $countTests;

    /** @var int $countErrors Number of test that had a fatal error in the test suite. */
    public $countErrors;

    /** @var int $countSkipped Number of tests that were skipped in the test suite. */
    public $countSkipped;

    /** @var int $countFailures Number of tests that failed in the test suite. */
    public $countFailures;

    /** @var JUnitTestCase[] $testCases Array with the test cases run inside this suite. */
    public $testCases = [];

    /**
     * Returns true iff all tests in this suite passed.
     */
    public function isPassed(): bool
    {
        foreach ($this->testCases as $tc) {
            if (!$tc->isPassed()) {
                return false;
            }
        }
        return true;
    }

}
