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

package uk.ac.aston.autofeedback.junitxml;

import org.junit.Ignore;
import org.junit.Test;

import static org.junit.Assert.assertTrue;
import static org.junit.Assert.fail;

/**
 * <p>Tests for checking what sort of JUnit XML is produced by Surefire. An unofficial JUnit XML spec
 * (which is sort of the "de facto" format for unit tests) is
 * <a href="https://llg.cubic.org/docs/junit/">here</a>.</p>
 *
 * <p>You can extract the console output of the tests per class with
 * {@code -Dmaven.test.redirectTestOutputToFile=true}.</p>
 */
public class SampleTest {

    @Test
    public void passingTest() {
        assertTrue("This should pass", true);
    }

    @Test
    public void errorTest() throws Exception {
        throw new Exception("unexpected error");
    }

    @Test
    public void failingTest() {
        assertTrue("This should fail", false);
    }

    @Ignore
    @Test
    public void skippedTest() {
        fail("Skipping");
    }

    @Test
    public void testWithStdout() {
        System.out.println("for stdout");
    }

    @Test
    public void testWithStderr() {
        System.err.println("for stderr");
    }
}
