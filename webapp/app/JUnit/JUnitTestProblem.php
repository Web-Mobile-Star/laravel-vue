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
 * Contains the details about a JUnit test failure or error.
 */
class JUnitTestProblem
{
    /** @var string $message Short message related to the problem (e.g. the message of the assertion or the exception). */
    public $message;

    /** @var string $type Type of problem (usually an exception class name, could be an AssertionError). */
    public $type;

    /** @var string $text Text produced by the problem (usually a stack trace). */
    public $text;
}
