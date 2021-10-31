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

namespace App\Jobs;

use App\Assessment;
use App\BuildResultFile;
use App\Events\MavenBuildJobStatusUpdated;
use App\Zip\ExtendedZipArchive;
use App\ZipSubmission;
use Exception;
use FilesystemIterator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use ZipArchive;

class MavenBuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @const int Code for the SIGTERM signal in UNIX systems.
     */
    const SIGTERM = 15;

    /**
     * @const string Command to run Maven in the java-worker Docker machine. 'exec' is used to have it replace
     *               the shell process that PHP starts, so proc_terminate will kill the build if it runs for
     *               too long.
     */
    const MAVEN_COMMAND = 'exec /usr/bin/mvn';

    /**
     * @const string Name of the environment variable in the java-worker Docker
     *               machine with the path to the Maven settings file to be used.
     */
    const MAVEN_SETTINGS_ENV = 'M2_SETTINGS';

    /**
     * @const string Name of the environment variable in the java-worker Docker
     *               machine with the path to the Java security policy to be used.
     */
    const POLICY_PATH_ENV = 'SUREFIRE_POLICY';

    /**
     * @var ZipSubmission File to be run as a Maven build.
     */
    public $submission;

    /**
     * @var int Time in seconds that this job is allowed to run for.
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param ZipSubmission $submission ZIP file with the Maven POM to be run.
     */
    public function __construct(ZipSubmission $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception Failed to delete the submission after running it.
     */
    public function handle()
    {
        Log::info("Running Maven build job for submission " . $this->submission->id . " with timeout of " . $this->timeout . "s");

        $this->submission->status = ZipSubmission::STATUS_RUNNING;
        $this->submission->save();
        event(new MavenBuildJobStatusUpdated($this->submission));

        // Delete old result files (if existing) - rerun
        foreach ($this->submission->resultFiles as $rf) {
            $rf->delete();
        }

        $mavenWorkingDirectory = $this->setUpMavenWorkingDirectory();
        $mavenCommand = self::getMavenCommand();
        $mavenDescriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', "$mavenWorkingDirectory/stdout.txt", "w"],
            2 => ['file', "$mavenWorkingDirectory/stderr.txt", "w"]
        ];
        $mavenEnv = [
            'PATH' => getenv('PATH'),
            'JAVA_HOME' => getenv('JAVA_HOME'),
            // Allows assessment creators to define AutoFeedback-specific Maven profiles
            'AUTOFEEDBACK' => 1,
        ];

        Log::info("Starting Maven build for '" . $this->submission->diskPath . "'");
        Log::debug("Running Maven command from $mavenWorkingDirectory: $mavenCommand");
        $process = proc_open($mavenCommand, $mavenDescriptors, $pipes, $mavenWorkingDirectory, $mavenEnv);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $returnValue = $this->waitForProcess($process);
            $this->submission->status = $returnValue;

            $stillExists = $this->submissionStillExists();
            if ($stillExists) {
                $this->submission->save();
            }
            event(new MavenBuildJobStatusUpdated($this->submission));

            if ($stillExists) {
                Log::info("Maven build for '"
                    . $this->submission->diskPath
                    . "' completed with return value $returnValue");

                $this->collectBuildResults($mavenWorkingDirectory, $mavenDescriptors[1][1], $mavenDescriptors[2][1]);
            } else {
                Log::info(
                    "Maven build for aborted job "
                    . $this->submission->id
                    . " completed with return value $returnValue: will not collect results");
            }
        } else {
            throw new Exception("Failed to run Maven with command $mavenCommand");
        }
    }

    /**
     * Waits for a process to complete and then returns its exit code. Note that this is a replacement for proc_close,
     * which completely blocks PHP execution and does not allow the Laravel job to time out normally.
     *
     * This function will also kill the process if the ZipSubmission was deleted - normally this will be because the
     * user submitted a new version that replaces the old one.
     *
     * @param $process resource Process started by proc_open to wait for.
     * @return int Exit code of the process, or {@link SIGTERM} for aborted runs due to replaced submissions.
     */
    private function waitForProcess($process) {
        $submissionId = $this->submission->id;

        do {
            $status = proc_get_status($process);
            if ($status['running']) {
                if (!$this->submissionStillExists()) {
                    Log::info("Maven build for job $submissionId aborted: submission was replaced.");
                    proc_terminate($process, self::SIGTERM);
                    return self::SIGTERM;
                }
                sleep(1);
            } else {
                return $status['exitcode'];
            }
        } while (true);
    }

    /**
     * Returns true if the submission for this job still exists (i.e. it has not been replaced by the user
     * with a new version).
     */
    private function submissionStillExists(): bool {
        return ZipSubmission::where('id', $this->submission->id)->exists();
    }

    /**
     * Returns an array with the full command to run Maven.
     *
     * @throws Exception One of the environment variables is incorrectly set up.
     */
    private static function getMavenCommand()
    {
        $mavenSettings = getenv(self::MAVEN_SETTINGS_ENV);
        if (!$mavenSettings) {
            throw new Exception(self::MAVEN_SETTINGS_ENV . ' has not been set');
        } else if (!is_readable($mavenSettings)) {
            throw new Exception("The Maven settings file at '$mavenSettings' is not readable");
        }

        $securityPolicy = getenv(self::POLICY_PATH_ENV);
        if (!$securityPolicy) {
            throw new Exception(self::POLICY_PATH_ENV . ' has not been set');
        } else if (!is_readable($securityPolicy)) {
            throw new Exception("The Java security policy file at '$securityPolicy' is not readable");
        }

        return implode(' ', [
            self::MAVEN_COMMAND,
            '-B', '--settings', $mavenSettings,
            '-DargLine=\'-Djava.security.manager -Djava.security.policy==' . $securityPolicy . '\'',
            'test'
        ]);
    }

    /**
     * Deletes a directory tree recursively.
     * @param string $dir Path to the directory to delete.
     * @return bool TRUE if successful, FALSE otherwise.
     */
    private static function delTree(string $dir): bool
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                self::delTree("$dir/$file");
            } else {
                unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }

    /**
     * Returns the path to the first file in $folder that matches the
     * regular expression $regex.
     *
     * @param $folder string Path to the folder to be searched.
     * @param $regex string Regular expression that the filename should match.
     * @return string|bool to the first match, or FALSE if no match was found.
     */
    private static function findFirst(string $folder, string $regex)
    {
        $dir = new RecursiveDirectoryIterator($folder);
        $ite = new RecursiveIteratorIterator($dir);
        $files = new RegexIterator($ite, $regex, RegexIterator::MATCH);

        foreach ($files as $file) {
            return $file->getPathName();
        }
        return FALSE;
    }

    /**
     * Unpacks the submission and returns the path to the POM inside it. The unpacked submission
     * will be automatically deleted after this PHP script completes execution.
     *
     * @return string Path to the working directory for the Maven build.
     * @throws Exception Could not unpack the ZIP file, or could not find a POM after unpacking it.
     */
    private function setUpMavenWorkingDirectory(): string
    {
        $tempSubmissionZipPath = $this->submission->saveToTemporaryFile();
        $tempFolder = $this->unzipSubmission($tempSubmissionZipPath);

        // Find the directory with the POM file (to use as cwd)
        $pomPath = self::findFirst($tempFolder, '/pom[.]xml/');
        if (!$pomPath) {
            throw new Exception("Could not find POM in the unzipped file");
        }
        $mavenWD = dirname($pomPath);

        // Unpack the overrides into the Maven working directory
        if ($this->submission->assessment) {
            $assessment = $this->submission->assessment->assessment;
            if ($assessment->fileOverrides->isNotEmpty()) {
                $this->unzipFileOverrides($assessment, $mavenWD);
            }
        }

        return $mavenWD;
    }

    /**
     * Creates {@link BuildResultFile}s from the build results of this Maven build.
     * @param string $dir Directory from which results should be collected.
     * @param string $stdoutPath Path to the standard output file.
     * @param string $stderrPath Path to the standard error file.
     */
    private function collectBuildResults(string $dir, string $stdoutPath, string $stderrPath) {
        BuildResultFile::createFrom($this->submission, 'stderr', $stderrPath, dirname($stderrPath));
        BuildResultFile::createFrom($this->submission, BuildResultFile::SOURCE_STDOUT, $stdoutPath, dirname($stdoutPath));

        $this->collectBuildResultsGlob(BuildResultFile::SOURCE_JUNIT, "$dir/target/surefire-reports", "TEST-*.xml");
        $this->collectBuildResultsRecursively('jacoco', "$dir/target/site/jacoco");
        $this->collectBuildResultsRecursively('jacoco-it', "$dir/target/site/jacoco-it");
        $this->collectBuildResultsRecursively('pit', "$dir/target/site/pit-reports");
    }

    /**
     * Collects build result files through a glob pattern. Only works for one level of
     * recursion.
     *
     * @param string $source Name of the source.
     * @param string $sourceRoot Root folder for the source.
     * @param string $pattern Glob pattern to filter files with.
     */
    private function collectBuildResultsGlob(string $source, string $sourceRoot, string $pattern): void
    {
        $junitResults = glob("$sourceRoot/$pattern");
        foreach ($junitResults as $r) {
            BuildResultFile::createFrom($this->submission, $source, $r, $sourceRoot);
        }
    }

    /**
     * Collects all the builds within a particular source.
     * @param string $source Name of the source.
     * @param string $folder Root folder for this source.
     */
    private function collectBuildResultsRecursively(string $source, string $folder): void
    {
        if (is_dir($folder)) {
            $itDir = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
            $ite = new RecursiveIteratorIterator($itDir);
            foreach ($ite as $r) {
                BuildResultFile::createFrom($this->submission, $source, $r, $folder);
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Exception|\Error  $exception
     * @return void
     */
    public function failed($exception)
    {
        // Send user notification of failure, etc...
        Log::error('Maven build job for submission ' . $this->submission->id . ' failed: ' . $exception);
        $this->submission->status = ZipSubmission::STATUS_ABORTED;
        $this->submission->save();
        event(new MavenBuildJobStatusUpdated($this->submission));
    }

    /**
     * @param string $tempSubmissionZipPath
     * @return string Path to the temporary folder used to unpack the submission.
     * @throws Exception
     */
    private function unzipSubmission(string $tempSubmissionZipPath): string
    {
        $tempFolder = tempnam(sys_get_temp_dir(), 'unzip' . $this->submission->id . '_');
        unlink($tempFolder);
        $zip = new ZipArchive();
        if ($zip->open($tempSubmissionZipPath) === TRUE) {
            try {
                if (!$zip->extractTo($tempFolder)) {
                    throw new Exception("Could not extract zip file " . $tempSubmissionZipPath);
                }
            } finally {
                $zip->close();
            }
        } else {
            throw new Exception("Could not read zip file " . $tempSubmissionZipPath);
        }
        register_shutdown_function(function () use ($tempFolder) {
            self::delTree($tempFolder);
        });

        return $tempFolder;
    }

    /**
     * @param Assessment $assessment
     * @param string $tempFolder
     * @throws Exception
     */
    private function unzipFileOverrides(Assessment $assessment, string $tempFolder): void
    {
        $modelSolution = $assessment->latestModelSolution;
        $this->submission->assessment->model_solution_id = $modelSolution->id;
        $this->submission->assessment->save();

        $modelSubmission = $modelSolution->submission;
        $tempModelZipPath = $modelSubmission->saveToTemporaryFile();

        $zip = new ExtendedZipArchive();
        if ($zip->open($tempModelZipPath) === TRUE) {
            try {
                foreach ($assessment->fileOverrides as $override) {
                    $targetPath = $tempFolder . '/' . $override->path;
                    $zip->extractName($override->path, $targetPath);
                }
            } finally {
                $zip->close();
            }
        } else {
            throw new Exception("Could not read model submission ZIP file " . $tempModelZipPath);
        }
    }
}
