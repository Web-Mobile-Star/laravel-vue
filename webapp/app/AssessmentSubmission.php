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

namespace App;

use App\Events\MavenBuildJobStatusUpdated;
use App\Jobs\CalculateChecksumJob;
use App\Jobs\MarkSubmissionJob;
use App\Jobs\MavenBuildJob;
use App\JUnit\JUnitTestCase;
use App\JUnit\JUnitTestSuite;
use App\JUnit\JUnitXMLParser;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Represents a submission towards an assessment from a student.
 *
 * @property int id Unique auto-incremented ID.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property int passed  Number of tests passed.
 * @property int skipped Number of tests skipped.
 * @property int errors  Number of tests that produced an unexpected error.
 * @property int failed  Number of tests that had an assertion fail.
 * @property int missing Number of tests that were expected but not executed (e.g. because of a compilation error).
 * @property string points Points achieved - use bcmath to perform operations.
 *                         Can be {@link AssessmentSubmission::POINTS_PENDING} if the marking has not been done yet.
 * @property int attempt Attempt counter, starting at 1.
 * @property int teaching_module_user_id ID of the {@link TeachingModuleUser} that authored the submission.
 * @property int zip_submission_id ID of the {@link ZipSubmission} that was submitted.
 * @property int assessment_id ID of the {@link Assessment} that this submission is for.
 * @property int model_solution_id ID of the {@link ModelSolution} that this submission has been run against, if any.
 *
 * @property Assessment assessment Assessment that this submission is for.
 * @property TeachingModuleUser author User that authored this submission.
 * @property ZipSubmission submission File that was submitted.
 * @property ?ModelSolution modelSolution Model solution that this submission has been run against.
 * @property AssessmentSubmission[] attempts Set of attempts for the same assessment by the same user,
 *   sorted by attempt.
 */
class AssessmentSubmission extends Model
{
    use HasFactory;

    const TASK_NOT_ASSIGNED = 'Tests not assigned to a task';
    const TASK_NOT_IN_MODEL_SOLUTION = 'Tests not in the model solution';
    protected $attributes = [
        'points' => self::POINTS_PENDING,
        'passed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'failed' => 0,
        'missing' => 0,
    ];

    /** @var string Special value for {@link AssessmentSubmission::$points} when the marking has not been done yet. */
    const POINTS_PENDING = '-4.04';

    public static function boot()
    {
        parent::boot();

        static::saving(function (AssessmentSubmission $sub) {
            $moduleAuthor = $sub->author->teaching_module_id;
            $moduleAssessment = $sub->assessment->usage->teaching_module_id;
            if ($moduleAuthor !== $moduleAssessment) {
                Log::error('The author must belong to the same module as the assessment.');
                return false;
            }
        });

        // Deleting the assessment submission deletes the zip file as well
        static::deleted(function (AssessmentSubmission $asub) {
            $asub->submission->delete();
        });
    }

    public function assessment(): BelongsTo {
        return $this->belongsTo('App\Assessment');
    }

    public function submission(): BelongsTo {
        return $this->belongsTo('App\ZipSubmission', 'zip_submission_id');
    }

    public function author(): BelongsTo {
        return $this->belongsTo('App\TeachingModuleUser', 'teaching_module_user_id');
    }

    public function modelSolution(): BelongsTo {
        return $this->belongsTo('App\ModelSolution', 'model_solution_id');
    }

    /**
     * Set of attempts by the same author for the same assessment.
     */
    public function attempts(): HasMany {
        return $this->assessment->submissionsFor($this->author)->orderBy('attempt');
    }

    /**
     * Returns the number of the latest attempt for this submission.
     */
    public function latestAttemptNumber(): int {
        $result = $this->assessment->submissionsFor($this->author)
            ->selectRaw('MAX(attempt) AS max_attempt')
            ->pluck('max_attempt');

        return $result->get(0);
    }

    /**
     * Returns a stringified version of the points for this submission. If the mark is pending,
     * returns the provided placeholder value.
     *
     * @param string $ifPending Value to return if points are pending. By default, this is the empty string.
     * @return string Points obtained if available, or $ifPending if the marks are still pending.
     */
    public function pointsAsString(string $ifPending = ''): string {
        if ($this->points == self::POINTS_PENDING) {
            return $ifPending;
        } else {
            return (string) $this->points;
        }
    }

    /**
     * Returns an array with all the test results from the JUnit files.
     * @return JUnitTestSuite[]
     */
    public function junitTestSuites() {
        $suites = [];
        $parser = new JUnitXMLParser;

        /** @var BuildResultFile[] $junitResultFiles */
        $junitResultFiles = $this->submission->resultFiles()->where('source', BuildResultFile::SOURCE_JUNIT)->orderBy('originalPath')->get();
        foreach ($junitResultFiles as $resultFile) {
            $tempFile = $resultFile->unpackIntoTemporaryFile();
            try {
                $suites[] = $parser->parse($tempFile);
            } catch (Exception $e) {
                Log::error("Failed to parse " . $resultFile->originalPath . ": " . $e);
            }
        }

        return $suites;
    }

    /**
     * Returns an array with virtual JUnit test suites with the missing tests:
     * these tests were expected to run, but they did not (usually because of
     * compilation failures).
     *
     * @return JUnitTestSuite[] Virtual JUnit test suites for missing tests.
     */
    public function missingTestSuites() {
        $testsByClass = $this->assessment->testsByClass();
        foreach ($this->junitTestSuites() as $suite) {
            foreach ($suite->testCases as $tc) {
                unset($testsByClass[$tc->className][$tc->name]);
            }
        }
        ksort($testsByClass);

        $missingTestSuites = [];
        foreach ($testsByClass as $class => $testsByName) {
            if ($testsByName) {
                $missingTestSuite = new JUnitTestSuite;
                $missingTestSuite->name = $class;
                $missingTestSuites[] = $missingTestSuite;

                foreach ($testsByName as $testName => $testPoints) {
                    $test = new JUnitTestCase;
                    $test->missing = true;
                    $test->name = $testName;
                    $test->className = $class;

                    $missingTestSuite->testCases[] = $test;
                }
            }
        }

        return $missingTestSuites;
    }

    /**
     * Schedules a re-run of this submission in the specified queue.
     *
     * @param string $queue Queue to be used: check the values in {@link ZipSubmission}.
     * @return AssessmentSubmission New assessment submission with a copy of the current submission.
     */
    public function rerun(string $queue = ZipSubmission::QUEUE_NORMAL): AssessmentSubmission
    {
        Log::info('Rerunning submission ' . $this->id . ' on queue ' . $queue);

        $newZipSubmission = $this->submission->copy();
        $newSubmission = new AssessmentSubmission;
        $newSubmission->assessment_id = $this->assessment_id;
        $newSubmission->teaching_module_user_id = $this->teaching_module_user_id;
        $newSubmission->zip_submission_id = $newZipSubmission->id;
        $newSubmission->attempt = $this->latestAttemptNumber() + 1;
        $newSubmission->save();
        $this->assessment->removeStaleAttemptsFromConfig($newSubmission->author);

        event(new MavenBuildJobStatusUpdated($newZipSubmission));
        MavenBuildJob::withChain([
            new MarkSubmissionJob($newSubmission->id),
            new CalculateChecksumJob($newZipSubmission->id),
        ])->onQueue($queue)->dispatch($newZipSubmission);

        return $newSubmission;
    }

    /**
     * Returns true if the assessment has a deadline and this submission was created after that deadline.
     */
    public function isLate() {
        $dueBy = $this->assessment->due_by;
        return $dueBy && $this->created_at->isAfter($dueBy);
    }

    /**
     * Returns a set of synthetic test suites, one per "task" (sorted in alphanumerical order), and then test
     * suites with all "other" tests in the model solution, and all tests not in the model solution. Tests are
     * ordered by class within each task suite, then by name.
     */
    public function taskTestSuites() {
        $junitTestSuites = $this->junitTestSuites();
        $missingTestSuites = $this->missingTestSuites();

        $junitTestsByClass = [];
        self::addAllTests($junitTestSuites, $junitTestsByClass);
        self::addAllTests($missingTestSuites, $junitTestsByClass);

        $otherTestsTask = __(self::TASK_NOT_ASSIGNED);
        $taskTestSuites = [];
        foreach ($this->assessment->tests as $t) {
            if (is_null($t->task)) {
                $t->task = $otherTestsTask;
            }

            if (array_key_exists($t->task, $taskTestSuites)) {
                $suite = $taskTestSuites[$t->task];
            } else {
                $suite = new JUnitTestSuite;
                $suite->name = $t->task;
                $taskTestSuites[$t->task] = $suite;
            }

            $suite->testCases[] = $junitTestsByClass[$t->class_name][$t->name];
            unset($junitTestsByClass[$t->class_name][$t->name]);
        }

        $nmTestsTask = __(self::TASK_NOT_IN_MODEL_SOLUTION);
        foreach ($junitTestsByClass as $className => $tests) {
            foreach ($tests as $t) {
                if (array_key_exists($nmTestsTask, $taskTestSuites)) {
                    $suite = $taskTestSuites[$nmTestsTask];
                } else {
                    $suite = new JUnitTestSuite;
                    $suite->name = $nmTestsTask;
                    $taskTestSuites[$nmTestsTask] = $suite;
                }

                $suite->testCases[] = $t;
            }
        }

        // Sort keys by task name, but with (not in task, not in model solution) last in that order
        uksort($taskTestSuites, function ($a, $b) use ($otherTestsTask, $nmTestsTask) {
            if ($a == $nmTestsTask) {
                return 1;
            } elseif ($b == $nmTestsTask) {
                return -1;
            } elseif ($a == $otherTestsTask) {
                return 1;
            } elseif ($b == $otherTestsTask) {
                return -1;
            } else {
                return strcmp($a, $b);
            }
        });

        // Within each suite, sort by class then by name
        foreach ($taskTestSuites as $ts) {
            usort($ts->testCases, function ($a, $b)  {
                $cmpClasses = strcmp($a->className, $b->className);
                if ($cmpClasses) {
                    return $cmpClasses;
                } else {
                    return strcmp($a->name, $b->name);
                }
            });
        }

        return array_values($taskTestSuites);
    }

    /**
     * Returns a query with the latest attempt from each student in each assessment.
     * Should be further filtered by student and/or assessment, and paginated.
     */
    public static function latest() {
        return AssessmentSubmission::query()
            ->joinSub(
                AssessmentSubmission::query()
                    ->select(['assessment_id', 'teaching_module_user_id'])
                    ->selectRaw('MAX(attempt) as latest_attempt')
                    ->groupBy('assessment_id', 'teaching_module_user_id'),
                'latest_attempts',
                function ($join) {
                    $join
                        ->on('assessment_submissions.assessment_id', '=', 'latest_attempts.assessment_id')
                        ->on('assessment_submissions.teaching_module_user_id', '=', 'latest_attempts.teaching_module_user_id')
                        ->on('assessment_submissions.attempt', '=', 'latest_attempts.latest_attempt');
                })
            ->select('assessment_submissions.*');
    }

    /**
     * @param array $testSuites
     * @param array $junitTestsByClass
     */
    private static function addAllTests(array &$testSuites, array &$junitTestsByClass)
    {
        foreach ($testSuites as $suite) {
            if (!array_key_exists($suite->name, $junitTestsByClass)) {
                $junitTestsByClass[$suite->name] = [];
            }
            foreach ($suite->testCases as $tc) {
                $junitTestsByClass[$suite->name][$tc->name] = $tc;
            }
        }
    }
}
