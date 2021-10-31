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
 * Contains the results of a single JUnit test case.
 */
class JUnitTestCase
{
    /** @var string $name Name of the test (e.g. method name).  */
    public $name;

    /** @var string $className Fully qualified name of the class. */
    public $className;

    /** @var float $timeSeconds Wall clock duration of the test, in seconds. */
    public $timeSeconds;

    /** @var ?JUnitTestProblem $failure Details on how this test failed, if not null. */
    public $failure;

    /** @var ?JUnitTestProblem $error Details on how this test had a fatal error, if not null. */
    public $error;

    /** @var bool $skipped True if this test was skipped, false otherwise. */
    public $skipped = false;

    /** @var bool $missing True if this test was missing from the run, false otherwise. */
    public $missing = false;

    /** @var ?string $stdout Text produced on the standard output, if any. */
    public $stdout;

    /** @var ?string $stderr Text produced on the standard error, if any.  */
    public $stderr;

    const STATUS_SKIPPED = 'skipped';
    const STATUS_FAILED = 'failed';
    const STATUS_MISSING = 'missing';
    const STATUS_ERRORED = 'errored';
    const STATUS_PASSED = 'passed';

    const STATUS_STRINGS = [
        self::STATUS_SKIPPED => 'Skipped',
        self::STATUS_FAILED => 'Failed assertion',
        self::STATUS_MISSING => 'Missing',
        self::STATUS_ERRORED => 'Unexpected error',
        self::STATUS_PASSED => 'Passed',
    ];

    /**
     * Returns true iff the test has run successfully (not skipped, not failed, not errored).
     */
    public function isPassed(): bool
    {
        return is_null($this->error) && is_null($this->failure) && !$this->skipped && !$this->missing;
    }

    /**
     * Returns true iff the test has not succeeded (either errored or failed). Skipped tests are considered successful.
     */
    public function isUnsuccessful(): bool
    {
        return isset($this->error) || isset($this->failure);
    }

    /**
     * Returns a localized string with the overall status of the test.
     */
    public function status(): string {
        return __(self::STATUS_STRINGS[$this->rawStatus()]);
    }

    /**
     * Returns a non-localized string with a string-based status code for this test, to be used from the API.
     */
    public function rawStatus(): string {
        if ($this->error) {
            return self::STATUS_ERRORED;
        } else if ($this->skipped) {
            return self::STATUS_SKIPPED;
        } else if ($this->failure) {
            return self::STATUS_FAILED;
        }  else if ($this->missing)  {
            return self::STATUS_MISSING;
        } else {
            return self::STATUS_PASSED;
        }
    }
}
