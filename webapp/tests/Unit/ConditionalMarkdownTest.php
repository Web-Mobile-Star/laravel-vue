<?php

/**
 *  Copyright 2021 Aston University
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
use App\JUnit\JUnitTestProblem;
use App\Markdown\TestMarkdownService;
use Tests\CreatesApplication;
use Tests\TestCase;

class ConditionalMarkdownTest extends TestCase
{
    use CreatesApplication;

    /**
     * @var TestMarkdownService
     */
    private $markdown;

    public function setUp(): void
    {
        $this->createApplication();
        $this->markdown = app(TestMarkdownService::class);
    }

    public function testSingleParagraph()
    {
        $this->assertMarkdownIs(null, '<p>example</p>', 'example');
    }

    public function testHideWithTest() {
        $this->assertMarkdownIs(new JUnitTestCase, '', "```af_hide\nfoo\n```");
    }

    public function testPassedWithPassedTestNoNesting() {
        $this->assertMarkdownIs(
            new JUnitTestCase,
            '<p>This should show.</p>',
            "```af_when_passed\nThis should show.\n```");
    }

    public function testPassedWithPassedTestCodeBlock() {
        // NOTE: the outer block uses four backticks, so it can only be clocked by a four-backtick line.
        // This allows us to nest fenced code blocks, by using fewer backticks.
        $html = $this->html(new JUnitTestCase(),
            "````af_when_passed\nTest.\n```java\nx;\n```\n````");

        $this->assertStringContainsString('<p>Test.</p>', $html);
        $this->assertStringContainsString('x;', $html);
        $this->assertStringContainsString(' hljs ', $html);
    }

    public function testPassedWithFailedTest() {
        $tc = new JUnitTestCase();
        $tc->error = new JUnitTestProblem;
        $this->assertMarkdownIs(
            $tc, '', "```af_when_passed\nThis should not show.\n```"
        );
    }

    public function testFailedWithPassedTest() {
        $this->assertMarkdownIs(new JUnitTestCase,
            '',
            "```af_when_failed\nThis should not show.\n```"
        );
    }

    public function testFailedWithFailedTest() {
        $tc = new JUnitTestCase();
        $tc->error = new JUnitTestProblem;
        $this->assertMarkdownIs($tc,
            '<p>This should show.</p>',
            "```af_when_failed\nThis should show.\n```"
        );
    }

    public function testSubstringWithoutOutput() {
        $this->assertMarkdownIs(new JUnitTestCase,
            '',
            "```af_when_substring Foo\nThis should not show.\n```"
        );
    }

    public function testSubstringWithMatchStderr() {
        $tc = new JUnitTestCase();
        $tc->stderr = 'Foo';
        $this->assertMarkdownIs($tc, '<p>This should show.</p>',
            "```af_when_substring Foo\nThis should show.\n```");
    }

    public function testSubstringWithMatchStdout() {
        $tc = new JUnitTestCase();
        $tc->stdout = 'Bar';
        $this->assertMarkdownIs($tc, '<p>This should show too.</p>',
            "```af_when_substring Bar\nThis should show too.\n```"
        );
    }

    public function testSubstringWithMatchTestError() {
        $tc = new JUnitTestCase();
        $tc->error = new JUnitTestProblem();
        $tc->error->text = 'Xyz';
        $this->assertMarkdownIs($tc, '<p>This should show too.</p>',
            "```af_when_substring Xyz\nThis should show too.\n```"
        );
    }

    public function testRegexWithoutMatch() {
        $this->assertMarkdownIs(new JUnitTestCase,
            '',
            "```af_when_regex P.*o\nThis should not show.\n```"
        );
    }

    public function testRegexWithMatchStderr() {
        $tc = new JUnitTestCase();
        $tc->stderr = 'Potato!';
        $this->assertMarkdownIs($tc, '<p>Expected potato!</p>',
            "```af_when_regex /P.*o/\nExpected potato!\n```"
        );
    }

    public function testRegexWithMatchStdout() {
        $tc = new JUnitTestCase();
        $tc->stdout = "Potato?\nPotatoes";
        $this->assertMarkdownIs($tc, '<p>Expected potatoes!</p>',
            "```af_when_regex /P.*s$/\nExpected potatoes!\n```"
        );
    }

    public function testRegexWithMatchTestFailure() {
        $tc = new JUnitTestCase();
        $tc->failure = new JUnitTestProblem();
        $tc->failure->text = 'MorePotatoes';
        $this->assertMarkdownIs($tc, '<p>Expected potatoes!</p>',
            "```af_when_regex /M.*P.*s$/\nExpected potatoes!\n```"
        );
    }

    public function testRegexCaseInsensitive() {
        $tc = new JUnitTestCase();
        $tc->stdout = 'LaTeX';
        $this->assertMarkdownIs($tc, '',
            "```af_when_regex /latex/\nThis should not show.\n```"
        );
        $this->assertMarkdownIs($tc, '<p>This should show.</p>',
            "```af_when_regex /latex/i\nThis should show.\n```"
        );
    }

    private function assertMarkdownIs(?JUnitTestCase $tc, string $expected, string $source) {
        $this->assertEquals($expected, $this->html($tc, $source));
    }

    private function html(?JUnitTestCase $tc, $source) {
        return trim($this->markdown->render($tc, $source));
    }
}
