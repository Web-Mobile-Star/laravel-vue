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

namespace App;

use App\Events\MavenBuildJobStatusUpdated;
use App\JUnit\JUnitTestCase;
use App\JUnit\JUnitTestSuite;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

/**
 * @property int id Unique auto-incremented ID.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property Carbon due_by Timestamp of the deadline within which students should make their submission.
 *
 * @property ModelSolution|null latestModelSolution Latest model solution for this assessment.
 * @property Collection|ModelSolution[] modelSolutions Model solutions for this assessment.
 * @property TeachingModuleItem usage {@link TeachingModuleItem} that uses this model as its content.
 * @property Collection|FileOverride[] fileOverrides Collection of {@link FileOverride}s associated to this assessment.
 * @property Collection|AssessmentTest[] sortedTests Collection of {@link AssessmentTest} associated to this assessment, sorted by class and then by name.
 * @property Collection|AssessmentTest[] tests Collection of {@link AssessmentTest}s associated to this assessment.
 * @property Collection|AssessmentSubmission[] submissions Collection of {@link AssessmentSubmission}s associated to this assessment.
 */
class Assessment extends Model
{
    use HasFactory;
    use Content;

    /** @var string Special value for {@link self::filteredSubmissions} to include/exclude outdated submissions. */
    const FILTER_OUTDATED = 'outdated';
    /** @var string Special value for {@link self::filteredSubmissions} to include/exclude submissions with full marks. */
    const FILTER_FULL_MARKS = 'fullMarks';
    /** @var string Special value for {@link self::filteredSubmissions} to include/exclude submissions with missing tests. */
    const FILTER_MISSING = 'missing';

    /** @var string Special value for {@link self::compareSubmissions} with the name of the field with the filename. */
    const COMPARE_FILENAME = 'filename';
    /** @var string Special value for {@link self::compareSubmissions} with the name of the field with the external SHA-256 checksum. */
    const COMPARE_SHA256_EXTERNAL = 'sha256_external';
    /** @var string Special value for {@link self::compareSubmissions} with the name of the field with the internal {@link AssessmentSubmission}. */
    const COMPARE_ASSESSMENT_SUBMISSION = 'asub';
    /** @var string Special value for {@link self::compareSubmissions} with the name of the field with the {@link TeachingModuleUser}. */
    const COMPARE_USER = 'tmu';
    /**
     * @var string Configuration key that specifies how many of the latest attempts should be kept. The latest
     * attempt with the maximum score is always kept.
     */
    public const CONFIG_KEEP_LATEST_ATTEMPTS = 'app.keep_latest_attempts';

    protected $fillable = [
        'due_by',
    ];

    protected $dates = [
        'due_by',
    ];

    /**
     * @inheritDoc
     */
    public function getIcon(): string
    {
        return 'fa-tasks';
    }

    /**
     * Returns the latest version of the model solution for this assessment, if there is any.
     */
    public function latestModelSolution(): HasOne
    {
        return $this->hasOne('App\ModelSolution')->orderByDesc('version')->limit(1);
    }

    /**
     * Returns the various versions of the model solution for this assessment.
     */
    public function modelSolutions(): HasMany {
        return $this->hasMany('App\ModelSolution');
    }

    /**
     * Returns the file overrides for this assessment. These are the paths which will
     * be extracted from the model solution, overwriting the student's files. These
     * are mostly useful to avoid unwanted modification of tests and test resources.
     */
    public function fileOverrides(): HasMany {
        return $this->hasMany('App\FileOverride');
    }

    /**
     * Returns the additional information for the tests in the model solution for this
     * assessment. This information includes the points to be awarded on pass, and the
     * feedback to be given on pass/fail, among other details.
     */
    public function tests(): HasMany {
        return $this->hasMany('App\AssessmentTest');
    }

    /**
     * Convenience version of {@link Assessment::tests()} which returns the tests
     * sorted by class, and then sorted by name.
     */
    public function sortedTests(): HasMany {
        return $this->tests()->orderBy('class_name')->orderBy('name');
    }

    /**
     * Returns the submissions done for this assessment. These include some summary
     * information (points achieved, test counts...).
     */
    public function submissions(): HasMany {
        return $this->hasMany('App\AssessmentSubmission');
    }

    /**
     * Returns the latest submissions done by each {@link TeachingModuleUser} for this assessment.
     */
    public function latestSubmissions() {
        return AssessmentSubmission::latest()
            ->where('latest_attempts.assessment_id', $this->id);
    }

    /**
     * Returns the submissions for a certain enrolment.
     * @param TeachingModuleUser $user
     * @return HasMany
     */
    public function submissionsFor(TeachingModuleUser $user): HasMany {
        return $user->submissions()->where('assessment_id', $this->id);
    }

    /**
     * Returns a list with 0/1 values: it will be empty if the user has not
     * submitted to this one yet, and it will have the latest submission from
     * a specific {@link TeachingModuleUser} to this assessment otherwise.
     */
    public function latestSubmissionFor(TeachingModuleUser $user) {
        return $user->latestSubmissions()
            ->where('assessment_submissions.assessment_id', '=', $this->id)
            ->limit(1);
    }

    /**
     * Returns a [path => selected] key-value array indicating which of the
     * files in the model solution are selected as overrides (true) or not
     * (false).
     */
    public function fileOverridesSelections(): array {
        $paths = $this->latestModelSolution->submission->getFilePathsInZIP();
        $pathSelection = [];
        foreach ($paths as $p) {
            $pathSelection[$p] = false;
        }
        foreach ($this->fileOverrides as $fo) {
            if (array_key_exists($fo->path, $pathSelection)) {
                $pathSelection[$fo->path] = true;
            }
        }
        return $pathSelection;
    }

    /**
     * Returns the {@link AssessmentTest} which matches the specified JUnit test case, if any exists.
     * @param JUnitTestCase $tc
     * @return AssessmentTest|null
     */
    public function testFor(JUnitTestCase $tc): ?AssessmentTest
    {
        /** @var AssessmentTest|null $test */
        return $this->tests
            ->where('class_name', $tc->className)
            ->where('name', $tc->name)
            ->first();
    }

    /**
     * Returns the maximum number of points achievable in this assessment.
     */
    public function achievablePoints(): string {
        $total = '0.00';
        foreach ($this->tests as $t) {
            $total = bcadd($t->points, $total, 2);
        }
        return $total;
    }

    /**
     * Returns an associative array in the form of class -> test -> points.
     */
    public function testsByClass(): array {
        $testsByClass = [];

        /** @var AssessmentTest $aTest */
        foreach ($this->tests as $aTest) {
            if (!isset($testsByClass[$aTest->class_name])) {
                $testsByClass[$aTest->class_name] = [];
            }
            $testsByClass[$aTest->class_name][$aTest->name] = $aTest->points;
        }

        return $testsByClass;
    }

    /**
     * Returns a class => test => countByType mapping with the counts from the various submissions in this
     * assessment. This may be sped up in the future by placing test results in the DB, instead of re-parsing
     * the XML files, if necessary.
     */
    public function countsByClass(): array {
        $counts = [];

        /**
         * @var AssessmentSubmission $asub
         */
        foreach ($this->latestSubmissions()->get() as $asub) {
            /**
             * @var JUnitTestSuite $junitSuite
             */
            foreach ($asub->junitTestSuites() as $junitSuite) {
                if (!isset($counts[$junitSuite->name])) {
                    $counts[$junitSuite->name] = [];
                }
                $suiteData = &$counts[$junitSuite->name];

                foreach ($junitSuite->testCases as $tc) {
                    if (!isset($suiteData[$tc->name])) {
                        $suiteData[$tc->name] = [
                            JUnitTestCase::STATUS_PASSED => 0,
                            JUnitTestCase::STATUS_FAILED => 0,
                            JUnitTestCase::STATUS_ERRORED => 0,
                            JUnitTestCase::STATUS_SKIPPED => 0,
                        ];
                    }

                    $testCounts = &$suiteData[$tc->name];
                    $testCounts[$tc->rawStatus()]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Passes all submissions of a given class and test through a certain callable.
     * @param string $className Name of the class (i.e. the JUnit suite name).
     * @param string $testName Name of the test (i.e. the JUnit test name).
     * @param callable $callable Callable taking (AssessmentSubmission, JUnitTestSuite, JUnitTestCase) to process the test submission.
     */
    public function processTestSubmissions(string $className, string $testName, callable $callable) {
        /** @var AssessmentSubmission $asub */
        foreach ($this->latestSubmissions()->get() as $asub) {
            /** @var JUnitTestSuite $testSuite */
            foreach ($asub->junitTestSuites() as $testSuite) {
                if ($testSuite->name == $className) {
                    foreach ($testSuite->testCases as $tc) {
                        if ($tc->name == $testName) {
                            $callable($asub, $testSuite, $tc);
                            break;
                        }
                    }
                    break;
                }
            }
        }
    }

    /**
     * Returns the latest submissions to this assessment, optionally filtered according to some predesigned conditions.
     *
     * @param array $options
     * Can have these keys, adding further conditions to the query (all keys are optional):
     * <ul>
     *   <li>{@link Assessment::FILTER_OUTDATED}:</li>
     *   <ul>
     *     <li>if set to true, will only keep the submissions that use model solutions previous to the latest one.</li>
     *     <li>if set to false, will only keep the submissions that used the latest model solution.</li>
     *   </ul>
     *   <li>{@link Assessment::FILTER_MISSING}:</li>
     *   <ul>
     *    <li>if set to true, will only keep the submissions that have missing tests.</li>
     *    <li>if set to false, will only keep the submissions that do not have any missing tests.</li>
     *   </ul>
     *   <li>{@link Assessment::FILTER_FULL_MARKS}:</li>
     *   <ul>
     *     <li>if set to true, will only keep the submissions that have full marks.</li>
     *     <li>if set to false, will only keep the submission that do not have full marks.</li>
     *   </ul>
     * </li>
     */
    public function filteredSubmissions(array $options = []) {
        $submissions = $this->latestSubmissions();

        if (array_key_exists(self::FILTER_OUTDATED, $options)) {
            $latestModelSolution = $this->latestModelSolution;
            if ($latestModelSolution) {
                if ($options[self::FILTER_OUTDATED]) {
                    $submissions = $submissions->where('model_solution_id', '!=', $latestModelSolution->id);
                } else {
                    $submissions = $submissions->where('model_solution_id', '=', $latestModelSolution->id);
                }
            }
        }

        if (array_key_exists(self::FILTER_MISSING, $options)) {
            if ($options[self::FILTER_MISSING]) {
                $submissions = $submissions->where('missing', '>', 0);
            } else {
                $submissions = $submissions->where('missing', '=', 0);
            }
        }

        if (array_key_exists(self::FILTER_FULL_MARKS, $options)) {
            if ($options[self::FILTER_FULL_MARKS]) {
                $submissions = $submissions->where('points', '=', $this->achievablePoints());
            } else {
                $submissions = $submissions->where('points', '!=', $this->achievablePoints());
            }
        }

        return $submissions;
    }

    /**
     * Returns the number of submissions that are older than the current model solution.
     */
    public function countOutdated(): int {
        return $this->filteredSubmissions([self::FILTER_OUTDATED => true])->count();
    }

    /**
     * Returns the number of submissions that have full marks.
     */
    public function countFullMarks(): int {
        return $this->filteredSubmissions([self::FILTER_FULL_MARKS => true])->count();
    }

    /**
     * Returns the number of submissions that have missing tests.
     */
    public function countMissing(): int {
        return $this->filteredSubmissions([self::FILTER_MISSING => true])->count();
    }


    /**
     * Compares the submissions in this assessment to those from the provided external system.
     *
     * @param array $submissionsByEmail Map from email to an array with {@link Assessment::COMPARE_FILENAME} and
     *           {@link Assessment::COMPARE_SHA256_EXTERNAL} key-value pairs.
     * @param mixed &$differentExternal Will be set to an array of associative arrays, where the associative arrays
     *           will have {@link Assessment::COMPARE_FILENAME}, {@link Assessment::COMPARE_SHA256_EXTERNAL} and
     *           {@link Assessment::COMPARE_USER} keys, and may have a {@link Assessment::COMPARE_ASSESSMENT_SUBMISSION} key.
     *           Each entry represents a submission in the external system which is mismatched with its AutoFeedback
     *           equivalent (AutoFeedback has a different file or no file).
     * @param mixed &$notInExternal Will be set to an array of associative arrays, where the associative arrays
     *           will have {@link Assessment::COMPARE_ASSESSMENT_SUBMISSION} and {@link Assessment::COMPARE_USER} keys.
     *           Each entry represents a submission in AutoFeedback for which there is no submission in the
     *           external system.
     * @param mixed &$notInModule Will be set to an flat array of emails for which there is no user in AutoFeedback.
     */
    public function compareSubmissions(array $submissionsByEmail, &$differentExternal, &$notInExternal, &$notInModule)
    {
        $differentExternal = [];
        $notInExternal = [];
        $notInModule = [];

        // 1. Start from the AutoFeedback submissions
        /** @var AssessmentSubmission $asub */
        foreach ($this->latestSubmissions()->with(['submission', 'author.user'])->get() as $asub) {
            $email = $asub->author->user->email;

            if (array_key_exists($email, $submissionsByEmail)) {
                $externalData = $submissionsByEmail[$email];
                unset($submissionsByEmail[$email]);

                $externalSHA256 = $externalData[self::COMPARE_SHA256_EXTERNAL];
                if ($externalSHA256 != $asub->submission->sha256) {
                    $differentExternal[] = [
                        self::COMPARE_USER => $asub->author,
                        self::COMPARE_SHA256_EXTERNAL => $externalSHA256,
                        self::COMPARE_ASSESSMENT_SUBMISSION => $asub,
                        self::COMPARE_FILENAME => $externalData[self::COMPARE_FILENAME],
                    ];
                }
            } else {
                $notInExternal[] = [
                    self::COMPARE_ASSESSMENT_SUBMISSION => $asub,
                    self::COMPARE_USER => $asub->author
                ];
            }
        }

        // 2. Start from the external submissions
        $moduleUsersByEmail = [];
        /** @var TeachingModuleUser $tmu */
        foreach ($this->usage->module->users()->with('user')->get() as $tmu) {
            $moduleUsersByEmail[$tmu->user->email] = $tmu;
        }
        foreach ($submissionsByEmail as $email => $externalData) {
            if (array_key_exists($email, $moduleUsersByEmail)) {
                $externalData = $submissionsByEmail[$email];
                $differentExternal[] = [
                    self::COMPARE_USER => $moduleUsersByEmail[$email],
                    self::COMPARE_FILENAME => $externalData[self::COMPARE_FILENAME],
                    self::COMPARE_SHA256_EXTERNAL => $externalData[self::COMPARE_SHA256_EXTERNAL],
                ];
            } else {
                $notInModule[] = $email;
            }
        }
    }

    /**
     * Convenience version which uses the number of attempts mentioned in the configuration file.
     */
    public function removeStaleAttemptsFromConfig(TeachingModuleUser $tmu): void {
        $keepAttempts = config(self::CONFIG_KEEP_LATEST_ATTEMPTS);
        if ($keepAttempts > 0) {
            $this->removeStaleAttemptsFrom($tmu, $keepAttempts);
        }
    }

    /**
     * Removes all the stale past attempts for a previous module user. This function will only
     * keep a certain number of the latest attempts, plus the highest-scoring attempt so far overall,
     * and the highest-scoring attempt before the deadline (there may be some overlap).
     *
     * @param TeachingModuleUser $tmu User whose attempts need to be cleaned up.
     * @param int $keepLatest Number of the latest attempts that will be preserved.
     */
    public function removeStaleAttemptsFrom(TeachingModuleUser $tmu, int $keepLatest): void
    {
        /** @var AssessmentSubmission[] $existing */
        $existing = $this->submissionsFor($tmu)->orderBy('attempt', 'desc')->get();
        $maxScore = '-1000.00';
        foreach ($existing as $s) {
            if (bccomp($s->points, $maxScore) > 0) {
                $maxScore = $s->points;
            }
        }

        $maxScoreBeforeDeadline = '-1000.00';
        if ($this->due_by) {
            foreach ($existing as $s) {
                if ($s->created_at->isBefore($this->due_by) && bccomp($s->points, $maxScoreBeforeDeadline) > 0) {
                    $maxScoreBeforeDeadline = $s->points;
                }
            }
        }

        $maxScoreBeforeDeadlineFound = false;
        $maxScoreFound = false;
        foreach ($existing as $i => $s) {
            $keep = false;
            if (!$maxScoreFound && $s->points == $maxScore) {
                // Attempt is the most recent one with the maximum overall score: keep
                $maxScoreFound = true;
                $keep = true;
            }
            if (!$maxScoreBeforeDeadlineFound && $s->created_at->isBefore($this->due_by) && $s->points == $maxScoreBeforeDeadline) {
                // Attempt is the most recent one before the deadline with the maximum overall score: keep
                $maxScoreBeforeDeadlineFound = true;
                $keep = true;
            }

            if (!$keep && $i >= $keepLatest) {
                // Attempt is none of the two special cases above, and it's after the latest N attempts: remove it
                Log::info(
                    "Removing stale attempt #{$s->attempt} with score {$s->points} "
                    . "out of stored " . count($existing) . " attempts from TMU {$tmu->id} "
                    . "for assessment {$this->id}: max score overall was {$maxScore}, max score before deadline was {$maxScoreBeforeDeadline}");

                $s->submission->status = ZipSubmission::STATUS_ABORTED;
                $s->delete();
                event(new MavenBuildJobStatusUpdated($s->submission));
            }
        }
    }

    /**
     * Returns true if this assessment has at least one test with a custom task group.
     */
    public function hasTasks(): bool
    {
        foreach ($this->tests as $t) {
            if ($t->task) return true;
        }
        return false;
    }

}
