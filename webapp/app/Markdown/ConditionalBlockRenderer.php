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

namespace App\Markdown;

use App\JUnit\JUnitTestCase;
use League\CommonMark\Block\Element\AbstractBlock;
use League\CommonMark\Block\Element\FencedCode;
use League\CommonMark\Block\Renderer\BlockRendererInterface;
use League\CommonMark\ElementRendererInterface;

class ConditionalBlockRenderer implements BlockRendererInterface
{
    const INFOLINE_HIDE = 'af_hide';
    const INFOLINE_PASSED = 'af_when_passed';
    const INFOLINE_FAILED = 'af_when_failed';
    const INFOLINE_SUBSTRING = 'af_when_substring';
    const INFOLINE_REGEX = 'af_when_regex';

    /**
     * @inheritDoc
     */
    public function render(AbstractBlock $block, ElementRendererInterface $htmlRenderer, bool $inTightList = false)
    {
        /** @var FencedCode $fBlock */
        $fBlock = $block;
        $infoString = trim($fBlock->getInfo());

        if (! app()->has('af.junit_test')) {
            return $this->renderExplanationCard($infoString, $block);
        }

        /** @var JUnitTestCase $tc */
        $tc = app('af.junit_test');
        $show = $this->computeShouldRender($infoString, $tc);
        if ($show) {
            // Re-parse the contents (unpeel one level of nesting)
            $source = $block->getStringContent();
            return app('markdown')->convertToHtml($source);
        } else if ($show === false) {
            return '';
        } else {
            return null;
        }
    }

    /**
     * @param string $infoString
     * @return string
     */
    private function getInfoLineRegex(string $infoString): string
    {
        $pattern = trim(substr($infoString, strlen(self::INFOLINE_REGEX)));
        if (!str_starts_with($pattern, '/')) {
            $pattern = "/$pattern/";
        }
        return $pattern;
    }

    /**
     * @param string $infoString
     * @return string
     */
    private function getInfoLineSubstring(string $infoString): string
    {
        $needle = trim(substr($infoString, strlen(self::INFOLINE_SUBSTRING)));
        return $needle;
    }

    /**
     * @param string $infoString
     * @param AbstractBlock $block
     * @return string|null
     */
    private function renderExplanationCard(string $infoString, AbstractBlock $block): ?string
    {
        $title = null;
        if (str_starts_with($infoString, self::INFOLINE_HIDE)) {
            $title = __('To be hidden');
        } else if (str_starts_with($infoString, self::INFOLINE_FAILED)) {
            $title = __('When test fails');
        } else if (str_starts_with($infoString, self::INFOLINE_PASSED)) {
            $title = __('When test passes');
        } else if (str_starts_with($infoString, self::INFOLINE_SUBSTRING)) {
            $needle = $this->getInfoLineSubstring($infoString);
            $title = __('When output contains ":text"', ["text" => $needle]);
        } else if (str_starts_with($infoString, self::INFOLINE_REGEX)) {
            $pattern = $this->getInfoLineRegex($infoString);
            $title = __('When output matches :pattern', ["pattern" => $pattern]);
        }

        if ($title !== null) {
            $source = $block->getStringContent();
            $body = app('markdown')->convertToHtml($source);
            return '<div class="card"><div class="card-body"><h5 class="card-title">' .
                $title . '</h5>' . $body . '</div></div>';
        }
        return null;
    }

    /**
     * @param string $infoString
     * @param JUnitTestCase $tc
     * @return bool|null
     */
    private function computeShouldRender(string $infoString, JUnitTestCase $tc): ?bool
    {
        $show = null;
        if (str_starts_with($infoString, self::INFOLINE_HIDE)) {
            $show = false;
        } else if (str_starts_with($infoString, self::INFOLINE_PASSED)) {
            $show = $tc && $tc->isPassed();
        } else if (str_starts_with($infoString, self::INFOLINE_FAILED)) {
            $show = $tc && !$tc->isPassed();
        } else if (str_starts_with($infoString, self::INFOLINE_SUBSTRING)) {
            $show = $this->isSubstringInTest($infoString, $tc);
        } else if (str_starts_with($infoString, self::INFOLINE_REGEX)) {
            $show = $this->isRegexMatchInTest($infoString, $tc);
        }
        return $show;
    }

    /**
     * @param string $infoString
     * @param JUnitTestCase $tc
     * @return bool
     */
    private function isSubstringInTest(string $infoString, JUnitTestCase $tc): bool
    {
        $needle = $this->getInfoLineSubstring($infoString);
        $show = $tc && (
                str_contains($tc->stderr ?? '', $needle) ||
                str_contains($tc->stdout ?? '', $needle) ||
                $tc->failure && (
                    str_contains($tc->failure->text, $needle) ||
                    str_contains($tc->failure->message, $needle)
                ) ||
                $tc->error && (
                    str_contains($tc->error->text, $needle) ||
                    str_contains($tc->error->message, $needle)
                )
            );
        return $show;
    }

    /**
     * @param string $infoString
     * @param JUnitTestCase $tc
     * @return bool
     */
    private function isRegexMatchInTest(string $infoString, JUnitTestCase $tc): bool
    {
        $pattern = $this->getInfoLineRegex($infoString);
        $show = $tc && (
                preg_match($pattern, $tc->stderr ?? '') ||
                preg_match($pattern, $tc->stdout ?? '') ||
                $tc->failure && (
                    preg_match($pattern, $tc->failure->text) ||
                    preg_match($pattern, $tc->failure->message)
                ) ||
                $tc->error && (
                    preg_match($pattern, $tc->error->text) ||
                    preg_match($pattern, $tc->error->message)
                )
            );
        return $show;
    }
}
