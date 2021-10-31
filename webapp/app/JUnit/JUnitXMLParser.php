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

use SimpleXMLElement;
use XMLReader;

/**
 * Parses JUnit XML reports into a PHP object-oriented structure.
 */
class JUnitXMLParser
{

    /**
     * Parses the specified JUnit XML file.
     *
     * @param string $path Path to the JUnit XML file. It is assumed that the file will follow the informal
     * spec mentioned in {@link https://llg.cubic.org/docs/junit/}.
     *
     * @return JUnitTestSuite
     *
     * @throws \ErrorException Failed to parse the JUnit XML file.
     */
    public function parse(string $path): JUnitTestSuite
    {
        $xml = new XMLReader();
        if ($xml->open($path)) {
            try {
                return $this->parseXML($xml);
            } finally {
                $xml->close();
            }
        }
    }

    /**
     * @param SimpleXMLElement $testElement
     * @return JUnitTestCase
     */
    private function parseTestCase(SimpleXMLElement $testElement): JUnitTestCase
    {
        $testCase = new JUnitTestCase;

        $testAttributes = $testElement->attributes();
        $testCase->name = (string) $testAttributes['name'];
        $testCase->className = (string) $testAttributes['classname'];
        $testCase->timeSeconds = floatval($testAttributes['time']);

        if ($testElement->failure) {
            $testCase->failure = $this->parseTestProblem($testElement->failure);
        }
        if ($testElement->error) {
            $testCase->error = $this->parseTestProblem($testElement->error);
        }
        $testCase->skipped = boolval($testElement->skipped);

        $testCase->stdout = (string) $testElement->{"system-out"};
        $testCase->stderr = (string) $testElement->{"system-err"};

        return $testCase;
    }

    /**
     * @param XMLReader $xml
     * @param JUnitTestSuite $junitTestSuite
     */
    private function parseTestSuiteTag(XMLReader $xml, JUnitTestSuite $junitTestSuite): void
    {
        $junitTestSuite->name = $xml->getAttribute('name');

        // time="0.084" tests="5" errors="0" skipped="1" failures="1"
        $junitTestSuite->timeSeconds = floatval($xml->getAttribute("time"));
        $junitTestSuite->countTests = intval($xml->getAttribute("tests"));
        $junitTestSuite->countErrors = intval($xml->getAttribute("errors"));
        $junitTestSuite->countSkipped = intval($xml->getAttribute("skipped"));
        $junitTestSuite->countFailures = intval($xml->getAttribute("failures"));
    }

    /**
     * Parses a JUnit test suite from an already created and opened {@link XMLReader}. The caller
     * is responsible for closing it.
     *
     * @param XMLReader $xml
     */
    public function parseXML(XMLReader $xml): JUnitTestSuite
    {
        $junitTestSuite = new JUnitTestSuite();

        while ($xml->read()) {
            if ($xml->nodeType == XMLReader::END_ELEMENT) {
                // skip closing tags
                continue;
            }

            if ($xml->name === 'testsuite') {
                $this->parseTestSuiteTag($xml, $junitTestSuite);
            } elseif ($xml->name === 'testcase') {
                $testElement = new SimpleXMLElement($xml->readOuterXml());
                $testCase = $this->parseTestCase($testElement);
                $junitTestSuite->testCases[] = $testCase;
            }
        }

        return $junitTestSuite;
    }

    /**
     * @param SimpleXMLElement $problemElement
     * @return JUnitTestProblem
     */
    private function parseTestProblem(SimpleXMLElement $problemElement): JUnitTestProblem
    {
        $failure = new JUnitTestProblem;
        $failure->message = $problemElement->attributes()['message'];
        $failure->type = $problemElement->attributes()['type'];
        $failure->text = (string)$problemElement;
        return $failure;
    }

}
