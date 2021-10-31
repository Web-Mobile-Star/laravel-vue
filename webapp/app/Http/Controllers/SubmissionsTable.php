<?php

/**
 * Copyright 2021 Aston University
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

namespace App\Http\Controllers;

use App\Assessment;
use App\TeachingModule;
use App\TeachingModuleUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Centralises the controller logic (and some of the view logic) for a sortable table of the submissions for a specific
 * assessment or student. Includes sortable column headers, and an option for showing all or some of the submissions.
 * Create an instance through the static methods, and use the public attributes in the view.
 */
class SubmissionsTable
{
    /** @var int Number of submissions to show per page. */
    const ITEMS_PER_PAGE = 50;

    /** @var string Query string key that specifies whether to sort in ascending ('asc') or descending ('desc') order. */
    const SORT_ORDER_QUERY_KEY = 'sortOrder';

    /** @var string Query string key that specifies the field to use for sorting. */
    const SORT_BY_QUERY_KEY = 'sortBy';

    /** @var string Query string that indicates that only the latest attempt for each user should be shown. */
    const SHOW_LATEST_KEY = 'latest';

    /** @var bool Whether to include the assessment column or not. */
    public $showAssessment = true;

    /** @var bool Whether to include the author column or not. */
    public $showAuthor = true;

    /** @var callable Function that produces the callable for all submissions. */
    private $allSubmissions;

    /** @var callable Function that produces the callable for the latest submissions. */
    private $latestSubmissions;

    /** @var callable Function that generates the URL generator by providing the 'show latest' option. */
    private $urlGeneratorFactory;

    /** @var callable Function that produces the callable to generate the column header URLs. */
    public $urlGenerator;

    /**
     * @var array|\string[][] Array of arrays of the form [sortKey, label] with the columns to be rendered in the view.
     */
    public $columns;

    /**
     * @var bool Whether we are showing the latest submissions or not.
     */
    public $showLatest = false;

    /**
     * @var string Column to use for the sorting.
     */
    public $columnSort;

    /**
     * @var string Whether to sort in ascending ('asc') or descending ('desc') order.
     */
    public $columnOrder;

    /**
     * Paginated collection with the submissions that we should go through.
     */
    public $submissions;

    /**
     * Sets up the table, based on the provided request.
     */
    private function handle(Request $request) {
        $this->validateSortOptions($request);

        $this->showLatest = (boolean) $request->get(self::SHOW_LATEST_KEY, false);
        $this->urlGenerator = ($this->urlGeneratorFactory)($this->showLatest);

        $this->columnSort = $request->get(self::SORT_BY_QUERY_KEY, 'id');
        $this->columnOrder = $request->get(self::SORT_ORDER_QUERY_KEY, 'asc');

        $submissions = $this->getSubmissions();

        $this->submissions = $this
            ->sortSubmissions($submissions)
            ->paginate(self::ITEMS_PER_PAGE)
            ->appends([
                self::SORT_BY_QUERY_KEY => $this->columnSort,
                self::SORT_ORDER_QUERY_KEY => $this->columnOrder,
                self::SHOW_LATEST_KEY => $this->showLatest,
            ]);
    }

    public static function forAssessment(Request $request, TeachingModule $module, Assessment $assessment): SubmissionsTable {
        $st = new SubmissionsTable();

        $st->showAssessment = false;
        $st->allSubmissions = function () use ($assessment) { return $assessment->latestSubmissions(); };
        $st->latestSubmissions = function () use ($assessment) { return $assessment->submissions(); };
        $st->urlGeneratorFactory = function  ($showLatest) use ($module, $assessment) {
            return function ($sortBy, $sortOrder) use ($module, $assessment, $showLatest) {
                return route('modules.assessments.showSubmissions', [
                    'module' => $module, 'assessment' => $assessment,
                    self::SORT_BY_QUERY_KEY => $sortBy,
                    self::SORT_ORDER_QUERY_KEY => $sortOrder,
                    self::SHOW_LATEST_KEY => $showLatest,
                ]);
            };
        };

        $st->handle($request);
        return $st;
    }

    public static function forAuthor(Request $request, TeachingModule $module, TeachingModuleUser $tmu): SubmissionsTable {
        $st = new SubmissionsTable();

        $st->showAuthor = false;
        $st->allSubmissions = function () use ($tmu) { return $tmu->latestSubmissions(); };
        $st->latestSubmissions = function () use ($tmu) { return $tmu->submissions(); };
        $st->urlGeneratorFactory = function  ($showLatest) use ($module, $tmu) {
            return function ($sortBy, $sortOrder) use ($module, $tmu, $showLatest) {
                return route('modules.users.show', [
                    'module' => $module, 'user' => $tmu->id,
                    self::SORT_BY_QUERY_KEY => $sortBy,
                    self::SORT_ORDER_QUERY_KEY => $sortOrder,
                    self::SHOW_LATEST_KEY => $showLatest,
                ]);
            };
        };

        $st->handle($request);
        return $st;
    }

    /**
     * @return string[]
     */
    private function getValidSortKeys(): array
    {
        $this->columns = array_merge(
            [['id', 'ID']],
            $this->showAssessment ? [['assessment', 'Assessment']] : [],
            $this->showAuthor ? [['author', 'Author']] : [],
            [
                ['created_at', 'Created at'],
                ['attempt', 'Attempt'], ['points', 'Marks'], ['passed', 'Passed'],
                ['failed', 'Failed'], ['errors', 'Errored'], ['skipped', 'Skipped'],
                ['missing', 'Missing'],
            ]
        );

        $keys = [];
        foreach ($this->columns as $col) {
            $keys[] = $col[0];
        }
        return $keys;
    }

    /**
     * @param Request $request
     */
    private function validateSortOptions(Request $request): void
    {
        $validSortKeys = $this->getValidSortKeys();
        $request->validate([
            self::SORT_ORDER_QUERY_KEY => 'sometimes|in:asc,desc',
            self::SORT_BY_QUERY_KEY => ['sometimes', Rule::in($validSortKeys)],
            self::SHOW_LATEST_KEY => 'sometimes|boolean',
        ]);
    }

    /**
     * @param bool $showLatest
     * @return mixed
     */
    private function getSubmissions()
    {
        if ($this->showLatest) {
            $submissions = ($this->allSubmissions)();
        } else {
            $submissions = ($this->latestSubmissions)();
        }
        return $submissions->with(['author.user', 'assessment']);
    }

    /**
     * @param $submissions
     * @param $columnSort
     * @param $columnOrder
     * @return mixed
     */
    private function sortSubmissions($submissions)
    {
        if ($this->columnSort == 'author') {
            $submissions = $submissions
                ->join('teaching_module_users',
                    'teaching_module_users.id', '=', 'assessment_submissions.teaching_module_user_id')
                ->join('users', 'users.id', '=', 'teaching_module_users.user_id')
                ->select('assessment_submissions.*')
                ->orderBy('users.name', $this->columnOrder);
        } else if ($this->columnSort == 'assessment') {
            $submissions = $submissions
                ->join('assessments', 'assessment_submissions.assessment_id', '=', 'assessments.id')
                ->join('teaching_module_items', function ($join) {
                    $join->on('teaching_module_items.content_id', '=', 'assessments.id')
                        ->where('teaching_module_items.content_type', '=', 'App\Assessment');
                })
                ->select('assessment_submissions.*')
                ->orderBy('teaching_module_items.title', $this->columnOrder);
        } else {
            $submissions = $submissions->orderBy($this->columnSort, $this->columnOrder);
        }

        if ($this->columnSort !== 'id') {
            // When tied, resolve ties with ID
            $submissions = $submissions->orderBy('id');
        }

        return $submissions;
    }

}
