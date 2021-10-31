<?php

/**
 *  Copyright 2020-2021 Aston University
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
use App\AssessmentSubmission;
use App\AssessmentTest;
use App\FileOverride;
use App\Jobs\CalculateChecksumJob;
use App\Jobs\MarkSubmissionJob;
use App\Jobs\MavenBuildJob;
use App\Jobs\SyncAssessmentTestsJob;
use App\JUnit\JUnitTestCase;
use App\ModelSolution;
use App\Policies\AssessmentPolicy;
use App\Rules\HasOnePOM;
use App\Rules\SameLengthAs;
use App\TeachingModule;
use App\User;
use App\ZipSubmission;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controller for managing most of an assessment, including batch processing of submissions.
 *
 * Comparisons with submissions in an external repository is handled in the {@link AssessmentSubmissionComparisonController},
 * and management of individual submissions is handled in the {@link AssessmentController}.
 */
class AssessmentController extends Controller
{
    /** @var int Number of submissions to show per page. */
    const ITEMS_PER_PAGE = 50;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * @return array
     */
    public static function submissionValidationRules(): array
    {
        return ['required', 'file', 'mimes:zip', 'max:' . ZipSubmission::MAX_FILE_SIZE_KB, new HasOnePOM];
    }

    /**
     * Show the form for showing the existing model solution (if any), and uploading/replacing it.
     *
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return View
     * @throws AuthorizationException
     */
    public function showModelSolution(TeachingModule $module, Assessment $assessment)
    {
        $this->authorize('viewModelSolution', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        return view('assessments.showSolution', [
            'module' => $module,
            'assessment' => $assessment,
            'submission' => $assessment->latestModelSolution ? $assessment->latestModelSolution->submission : null,
            'countSubmissions' => $assessment->latestSubmissions()->count(),
            'countOutdated' => $assessment->countOutdated(),
            'countFullMarks' => $assessment->countFullMarks(),
            'countMissing' => $assessment->countMissing(),
        ]);
    }

    /**
     * Replaces the currently stored model solution with a new one.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function storeModelSolution(Request $request, TeachingModule $module, Assessment $assessment)
    {
        $this->authorize('uploadModelSolution', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        $request->validate([
            'jobfile' => self::submissionValidationRules(),
        ]);

        try {
            $submission = DB::transaction(function () use ($request, $module, $assessment) {
                $jobFile = $request->file('jobfile');
                $submission = ZipSubmission::createFromUploadedFile($jobFile, Auth::id(), Auth::id());

                $modelSolution = new ModelSolution;
                $modelSolution->assessment_id = $assessment->id;
                $modelSolution->zip_submission_id = $submission->id;

                $latestModelSolution = $assessment->latestModelSolution;
                if ($latestModelSolution) {
                    $modelSolution->version = $latestModelSolution->version + 1;
                }
                $modelSolution->save();

                return $submission;
            });

            MavenBuildJob::withChain([
                new SyncAssessmentTestsJob($assessment),
                new CalculateChecksumJob($submission->id),
            ])->onQueue(ZipSubmission::QUEUE_HIGH)->dispatch($submission);

            return redirect()
                ->route('modules.assessments.showModelSolution', ['module' => $module->id, 'assessment' => $assessment->id])
                ->with('status', __('Added new model solution and sent to build queue.'));

        }  catch (Exception $e) {
            Log::error('Could not delete old model solution:\n' . $e);
            return redirect()
                ->route('modules.assessments.showModelSolution', ['module' => $module->id, 'assessment' => $assessment->id])
                ->with('warning', __('Could not delete model submission.'));
        }
    }

    /**
     * Deletes the model solution. Does not delete any test information or overrides (they may be reused later).
     *
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function destroyModelSolution(TeachingModule $module, Assessment $assessment)
    {
        $this->authorize('deleteModelSolution', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        try {
            return DB::transaction(function () use ($module, $assessment) {
                $currentModelSolution = $assessment->latestModelSolution;
                if ($currentModelSolution) {
                    $currentModelSolution->delete();
                }

                return redirect()
                    ->route('modules.items.show', ['module' => $module->id, 'item' => $assessment->usage->id])
                    ->with('status', __('Deleted the latest model solution successfully.'));
            });
        }   catch (Exception $e) {
            Log::error('Could not clear the model solution:\n' . $e);
            return redirect()
                ->route('modules.items.show', ['module' => $module->id, 'item' => $assessment->usage->id])
                ->with('warning', __('Could not clear the model solution.'));
        }
    }

    /**
     * Show the form for showing the existing file overrides (if any), and editing them (if allowed).
     *
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return View
     * @throws AuthorizationException
     */
    public function showOverrides(TeachingModule $module, Assessment $assessment)
    {
        $this->authorize('viewOverrides', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        if ($assessment->latestModelSolution) {
            $selections = $assessment->fileOverridesSelections();
            ksort($selections);
        } else {
            $selections = [];
        }

        /** @var User $user */
        $user = Auth::user();
        $canModify = $user->can('modifyOverrides', $assessment);

        return view('assessments.showOverrides', [
            'module' => $module,
            'assessment' => $assessment,
            'selections' => $selections,
            'canModify' => $canModify,
        ]);
    }

    /**
     * Stores the selected file overrides.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return View
     * @throws AuthorizationException
     */
    public function storeOverrides(Request $request, TeachingModule $module, Assessment $assessment)
    {
        $this->authorize('modifyOverrides', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        $request->validate([
            'paths' => 'sometimes|array',
            'paths.*' => 'sometimes|string|min:1',
        ]);

        $selectedPaths = $request->get('paths');
        return DB::transaction(function() use ($module, $assessment, $selectedPaths) {
            // Delete any overrides that are not selected
            foreach ($assessment->fileOverrides as $fo) {
                /** @var FileOverride $fo */
                if (!in_array($fo->path, $selectedPaths)) {
                    $fo->delete();
                }
            }

            // Add any missing overrides
            foreach ($selectedPaths as $p) {
                FileOverride::firstOrCreate([
                    'path' => $p,
                    'assessment_id' => $assessment->id
                ]);
            }

            return redirect()
                ->route('modules.assessments.showOverrides', [
                    'module' => $module->id,
                    'assessment' => $assessment->id,
                ])
                ->with('status', __('File overrides updated successfully.'));
        });
    }

    /**
     * Show the feedback information for the tests (if any).
     *
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return View
     * @throws AuthorizationException
     */
    public function showTests(TeachingModule $module, Assessment $assessment) {
        $this->authorize('viewTests', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        return view('assessments.showTests', [
            'module' => $module,
            'assessment' => $assessment,
            'tests' => $assessment->sortedTests,
        ]);
    }

    /**
     * Show the form to edit the feedback information for the tests.
     *
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return View
     * @throws AuthorizationException
     */
    public function editTests(TeachingModule $module, Assessment $assessment) {
        $this->authorize('modifyTests', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        return view('assessments.editTests', [
            'module' => $module,
            'assessment' => $assessment,
            'tests' => $assessment->sortedTests,
        ]);
    }

    /**
     * Updates the information for the tests in the assessment.
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @throws AuthorizationException
     */
    public function storeTests(Request $request, TeachingModule $module, Assessment $assessment) {
        $this->authorize('modifyTests', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        $request->validate([
            'id' => ['array'],
            'id.*' => ['integer', Rule::in($assessment->tests()->pluck('id')->toArray())],
            'points' => ['array', new SameLengthAs('id')],
            'points.*' => ['numeric', 'min:0', 'max:100'],
            'feedback' => ['array', new SameLengthAs('id')],
            'feedback.*' => ['max:10000'],
            'tasks' => ['array', new SameLengthAs('id')],
            'tasks.*' => ['nullable', 'string']
        ]);

        $marksChanged = false;
        foreach ($request->get('id') as $i => $id) {
            /** @var AssessmentTest $test */
            $test = AssessmentTest::findOrFail($id);

            $newPoints = $request->get('points')[$i];
            if ($test->points != $newPoints) {
                $test->points = $newPoints;
                $marksChanged = true;
            }
            $test->feedback_markdown = $request->get('feedback')[$i] ?: '';
            $test->task = $request->get('tasks')[$i] ?: null;
            $test->save();
        }

        if ($marksChanged) {
            // After a change in the test marking, we need to mark all executed submissions again
            foreach ($assessment->latestSubmissions()->with('submission')->get() as $asub) {
                $status = $asub->submission->status;
                if ($status != ZipSubmission::STATUS_ABORTED && $status != ZipSubmission::STATUS_PENDING) {
                    MarkSubmissionJob::dispatch($asub->id);
                }
            }
        }

        return redirect()
            ->route('modules.assessments.showTests', ['module' => $module->id, 'assessment' => $assessment->id])
            ->with('status', __('Updated tests successfully.'));
    }

    /**
     * Uploads a submission, for automated compilation and test execution.
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @throws AuthorizationException
     */
    public function storeSubmission(Request $request, TeachingModule $module, Assessment $assessment) {
        $this->authorize('uploadSubmission', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        // NOTE: check for consistency with the basic HTTP API version in API/AssessmentController
        $request->validate([
            'jobfile' => self::submissionValidationRules(),
            'feedbackIntentUnderstood' => 'required',
        ]);

        try {
            self::processSubmission($module, $assessment, $request);

            return redirect()
                ->route('modules.items.show', ['module' => $module->id, 'item' => $assessment->usage->id])
                ->with('status', __('Uploaded submission and sent to build queue.'));
        }  catch (Exception $e) {
            Log::error('Could not replace old submission:\n' . $e);
            return redirect()
                ->route('modules.items.show', ['module' => $module->id, 'item' => $assessment->usage->id])
                ->with('warning', __('Could not replace old submission.'));
        }
    }

    /**
     * Shows a paged sortable table with all the users on this module, and their current submission.
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @throws AuthorizationException
     */
    public function showSubmissions(Request $request, TeachingModule $module, Assessment $assessment) {
        $this->authorize('viewSubmissions', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);
        $submissions = SubmissionsTable::forAssessment($request, $module, $assessment);
        return view('assessments.showSubmissions', [
            'module' => $module,
            'assessment' => $assessment,
            'submissions' => $submissions,
        ]);
    }

    /**
     * Generates a CSV file with the summarized results of all submissions to this assessment.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     */
    public function downloadSubmissionsCSV(Request $request, TeachingModule $module, Assessment $assessment) {
        $this->authorize('viewSubmissions', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);
        $request->validate([
            SubmissionsTable::SHOW_LATEST_KEY => 'sometimes|boolean',
        ]);

        $showLatest = (boolean) $request->get(SubmissionsTable::SHOW_LATEST_KEY, false);
        $now = Carbon::now()->format('Ymd_His');
        $filename = 'submissions-' . $now . '_' . $module->name . '_a' . $assessment->id
            . ($showLatest ? '_latest' : '_all') . '.csv';

        $headers = array(
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=" . $filename,
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        if ($showLatest) {
            $submissions = $assessment->latestSubmissions();
        } else {
            $submissions = $assessment->submissions();
        }
        $submissions = $submissions->with([
            'assessment', 'assessment.latestModelSolution', 'submission', 'author', 'author.user'
        ])->get();

        $achievable = $assessment->achievablePoints();
        $callback = function() use ($module, $assessment, $submissions, $achievable)
        {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Module ID', 'Module Name',
                'Assessment ID', 'Assessment Title',
                'Submission ID', 'Author Name', 'Author Email',
                'Created at', 'Updated at', 'Attempt', 'Late', 'Model Solution Version', 'Outdated',
                'Achieved Marks', 'Achievable Marks',
                'ZIP Filename', 'ZIP SHA-256',
                'Passed', 'Failed', 'Errored', 'Skipped', 'Missing'
            ]);
            /** @var AssessmentSubmission $asub */
            foreach($submissions as $asub) {
                fputcsv($file, [
                    $module->id, $module->name,
                    $assessment->id, $assessment->usage->title,
                    $asub->id, $asub->author->user->name, $asub->author->user->email,
                    $asub->created_at, $asub->updated_at, $asub->attempt, $asub->isLate(),
                    $asub->modelSolution ? $asub->modelSolution->version : '',
                    $asub->modelSolution ? !$asub->modelSolution->isLatest() : '',
                    $asub->pointsAsString(), $achievable,
                    $asub->submission->filename, $asub->submission->sha256,
                    $asub->passed, $asub->failed, $asub->errors, $asub->skipped, $asub->missing,
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Reruns all or a subset of the submissions for a given assessment, in a low priority queue.
     * @param Request $request
     * @param TeachingModule $module
     * @param Assessment $assessment
     */
    public function rerunSubmissions(Request $request, TeachingModule $module, Assessment $assessment) {
        $this->authorize('rerunSubmissions', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        $request->validate([
            'rerunInclude' => 'in:all,outdated,missing',
            'skipFullMarks' => 'sometimes|boolean'
        ]);

        $filters = [];
        // INCLUDE FILTERS
        $include = $request->get('rerunInclude');
        if ($include == 'outdated') {
            $filters[Assessment::FILTER_OUTDATED] = true;
        } else if ($include == 'missing') {
            $filters[Assessment::FILTER_MISSING] = true;
        }
        // EXCLUDE FILTERS
        if ((boolean) $request->get('skipFullMarks')) {
            $filters[Assessment::FILTER_FULL_MARKS] = false;
        }

        $selected = $assessment->filteredSubmissions($filters)->with('submission')->get();
        /** @var AssessmentSubmission $asub */
        $count = 0;
        foreach ($selected as $asub) {
            $asub->rerun(ZipSubmission::QUEUE_LOW);
            ++$count;
        }

        return redirect(URL::previous())
            ->with('status', __('Scheduled :count :word to be re-run and re-marked.', [
                'count' => $count,
                'word' => Str::plural(__('submission'), $count)
            ]));
    }

    /**
     * Shows the starting HTML for the cohort progress visualisation.
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @throws AuthorizationException
     */
    public function viewProgressChart(TeachingModule $module, Assessment  $assessment) {
        $this->authorize('viewProgress', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        return view('assessments.cohortProgress', [
            'module' => $module,
            'assessment' => $assessment,
        ]);
    }

    /**
     * Produces a JSON representation of the overall progress of the cohort on this submission.
     * Used in the progress chart D3.js visualisation.
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function jsonProgressStats(TeachingModule $module, Assessment $assessment) {
        $this->authorize('viewProgress', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        $counts = $assessment->countsByClass();

        // We need to reshape the counts into the format expected by D3.js

        $data = [
            'name' => 'Assessment',
            'children' => []
        ];
        $classes = [];

        /**
         * @var AssessmentTest $test
         */
        $nUsers = $module->users()->count();
        foreach ($assessment->tests as $test) {
            if (!isset($classes[$test->class_name])) {
                // Simplify the class name a bit (helps when we have many classes)
                $classes[$test->class_name] = [
                    'name' => $test->class_name,
                    'children' => []
                ];
            }

            $testData = &$counts[$test->class_name][$test->name];
            $passed = $testData['passed'] ?? 0;
            $failed = $testData['failed'] ?? 0;
            $errored = $testData['errored'] ?? 0;
            $skipped = $testData['skipped'] ?? 0;

            $classes[$test->class_name]['children'][] = [
                'name' => $test->name,
                'children' => [
                    ['name' => 'passed', 'value'  => $passed],
                    ['name' => 'failed', 'value'  => $failed],
                    ['name' => 'errored', 'value' => $errored],
                    ['name' => 'skipped', 'value' => $skipped],
                    ['name' => 'missing', 'value' => $nUsers - $passed - $failed - $errored - $skipped]
                ]
            ];
        }
        foreach ($classes as $class) {
            $data['children'][] = $class;
        }

        return response()->json($data);
    }

    public function jsonClassTestStatus(TeachingModule $module, Assessment $assessment, string $className, string $testName, string $status) {
        $this->authorize('viewProgress', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        if ($status != 'missing') {
            $resultsHolder = new class {};
            $resultsHolder->data = [];

            $assessment->processTestSubmissions($className, $testName, function($sub, $testSuite, $tc) use ($resultsHolder, $status) {
                /** @var JUnitTestCase $tc */
                if ($tc->rawStatus() == $status) {
                    $resultsHolder->data[] = [
                        'submission_id' => $sub->id,
                        'user_id' => $sub->submission->user->id,
                        'user_name' => $sub->submission->user->name,
                    ];
                }
            });

            $data = $resultsHolder->data;
        } else {
            $tmuHolder = new class {};
            $tmuHolder->users = [];
            foreach ($module->users()->with('user:id,name')->get(['id', 'user_id']) as $tmu) {
                $tmuHolder->users[$tmu->id] = [
                    'user_id' => $tmu->user->id,
                    'user_name' => $tmu->user->name
                ];
            }

            $assessment->processTestSubmissions($className, $testName, function($sub, $testSuite, $tc) use ($tmuHolder) {
                /** @var AssessmentSubmission $sub */
                $sub_tmu_id = $sub->teaching_module_user_id;
                unset($tmuHolder->users[$sub_tmu_id]);
            });

            $data = array_values($tmuHolder->users);
        }

        usort($data, function ($a, $b) { return strcmp($a['user_name'], $b['user_name']); });
        return response()->json($data);
    }

    /**
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @param Request $request
     * @param User|null $author     {@link User} that authored the code in the submission.
     * @param User|null $submitter  {@link User} that did the submission (may or may not be the author).
     * @return AssessmentSubmission
     */
    public static function processSubmission(TeachingModule $module, Assessment $assessment, Request $request, $author = null, $submitter = null): AssessmentSubmission
    {
        if (is_null($author)) {
            $author = Auth::user();
        }
        if (is_null($submitter)) {
            $submitter = $author;
        }

        /** @var AssessmentSubmission $asub */
        $asub = DB::transaction(function () use ($request, $module, $assessment, $author, $submitter) {
            /** @var User $author */
            $tmu = $author->moduleUser($module);
            /** @var AssessmentSubmission $oldSubmission */
            $oldSubmission = $assessment->latestSubmissionFor($tmu)->first();

            $jobFile = $request->file('jobfile');
            $submission = ZipSubmission::createFromUploadedFile($jobFile, $author->id, $submitter->id);

            $asub = new AssessmentSubmission;
            $asub->assessment_id = $assessment->id;
            $asub->teaching_module_user_id = $tmu->id;
            $asub->zip_submission_id = $submission->id;
            $asub->attempt = $oldSubmission ? $oldSubmission->attempt + 1 : 1;
            $asub->save();

            if (!is_null($oldSubmission)) {
                $assessment->removeStaleAttemptsFromConfig($tmu);
            }

            return $asub;
        });

        MavenBuildJob::withChain([
            new MarkSubmissionJob($asub->id),
            new CalculateChecksumJob($asub->submission->id),
        ])->onQueue(ZipSubmission::QUEUE_NORMAL)->dispatch($asub->submission);

        return $asub;
    }

}

