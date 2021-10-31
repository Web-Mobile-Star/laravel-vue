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

namespace Tests\Unit;

use App\JUnit\JUnitTestCase;
use App\JUnit\JUnitXMLParser;
use PHPUnit\Framework\TestCase;

class JUnitXMLParserTest extends TestCase
{
    const SAMPLE_FILE = "test-resources/junit-xml-sample/sample/TEST-uk.ac.aston.autofeedback.junitxml.SampleTest.xml";

    public function testParseSample()
    {
        $junitParser = new JUnitXMLParser;
        $junitTestSuite = $junitParser->parse(self::SAMPLE_FILE);

        $this->assertEquals("uk.ac.aston.autofeedback.junitxml.SampleTest", $junitTestSuite->name);
        $this->assertEquals(0.082, $junitTestSuite->timeSeconds, 0.001);
        $this->assertEquals(6, $junitTestSuite->countTests);
        $this->assertEquals(1, $junitTestSuite->countErrors);
        $this->assertEquals(1, $junitTestSuite->countSkipped);
        $this->assertEquals(1, $junitTestSuite->countFailures);
        $this->assertCount(6, $junitTestSuite->testCases);
        $this->assertEquals(false, $junitTestSuite->isPassed());

        $failingTest = $junitTestSuite->testCases[0];
        $this->assertEquals("failingTest", $failingTest->name);
        $this->assertEquals("uk.ac.aston.autofeedback.junitxml.SampleTest", $failingTest->className);
        $this->assertEquals(0.009, $failingTest->timeSeconds, 0.001);
        $this->assertFalse($failingTest->skipped);
        $this->assertEquals("This should fail", $failingTest->failure->message);
        $this->assertEquals("java.lang.AssertionError", $failingTest->failure->type);
        $this->assertStringContainsString(
            "at uk.ac.aston.autofeedback.junitxml.SampleTest.failingTest", $failingTest->failure->text);
        $this->assertFalse($failingTest->isPassed());
        $this->assertTrue($failingTest->isUnsuccessful());

        $errorTest = $junitTestSuite->testCases[1];
        $this->assertEquals("errorTest", $errorTest->name);
        $this->assertFalse($errorTest->skipped);
        $this->assertEquals("unexpected error", $errorTest->error->message);
        $this->assertFalse($errorTest->isPassed());
        $this->assertTrue($errorTest->isUnsuccessful());

        $skippedTest = $junitTestSuite->testCases[2];
        $this->assertEquals("skippedTest", $skippedTest->name);
        $this->assertTrue($skippedTest->skipped);
        $this->assertFalse($skippedTest->isPassed());
        $this->assertFalse($skippedTest->isUnsuccessful());

        $stderrTest = $junitTestSuite->testCases[3];
        $this->assertEquals("testWithStderr", $stderrTest->name);
        $this->assertStringContainsString("for stderr", $stderrTest->stderr);
        $this->assertTrue($stderrTest->isPassed());
        $this->assertFalse($stderrTest->isUnsuccessful());

        $stdoutTest = $junitTestSuite->testCases[4];
        $this->assertEquals("testWithStdout", $stdoutTest->name);
        $this->assertStringContainsString("for stdout", $stdoutTest->stdout);
        $this->assertTrue($stdoutTest->isPassed());
        $this->assertFalse($stdoutTest->isUnsuccessful());

        $passingTest = $junitTestSuite->testCases[5];
        $this->assertEquals("passingTest", $passingTest->name);
        $this->assertTrue($passingTest->isPassed());
        $this->assertFalse($passingTest->isUnsuccessful());
    }
}
