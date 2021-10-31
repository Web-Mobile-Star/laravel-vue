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

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * @property int id Unique auto-incremented identifier for the submission.
 * @property string filename Name of the original submitted ZIP file.
 * @property string diskPath Path to the submission file within the Laravel disk.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property int user_id Foreign key to the {@link User} that authored this ZIP file.
 * @property int submitter_user_id Foreign key to the {@link User} that submitted this ZIP file (may or may not
 *  be the same as user_id).
 * @property int status Current status of the job: {@link ZipSubmission::STATUS_PENDING} if pending,
 *  {@link ZipSubmission::STATUS_RUNNING} if currently running, {@link ZipSubmission::STATUS_OK} if
 * completed successfullly, positive non-zero value if completed with an error.
 * @property string sha256 SHA256 checksum of the file, or {@link ZipSubmission::SHA256_PENDING} if not computed yet.
 *
 * @property User user User that authored this ZIP file.
 * @property User submitter User that submitted this ZIP file.
 * @property BuildResultFile[] resultFiles Files produced by this submission.
 * @property ModelSolution|null modelSolution Model solution which uses this file, if any.
 * @property AssessmentSubmission|null assessment Assessment submission which uses this file, if any.
 */
class ZipSubmission extends Model
{
    use HasFactory;

    /** @var int Job aborted in the worker queue. */
    const STATUS_ABORTED = -3;

    /** @var int Job currently running in a worker. */
    const STATUS_RUNNING = -2;

    /** @var int Job not picked up by a worker yet. */
    const STATUS_PENDING = -1;

    /** @var int Job completed with 0 status code. */
    const STATUS_OK = 0;

    /** @var string Temporary value assigned to submissions that have not had a SHA256 checksum computed yet. */
    const SHA256_PENDING = 'pending';

    // Note: >0 status means "completed but with a non-zero status code (e.g. Maven failed to build).

    /** @var int Maximum size for uploaded jobs, in kilobytes. */
    public const MAX_FILE_SIZE_KB = 10000;

    /** @var string Name of the worker queue with normal priority (raw jobs, student submissions). */
    public const QUEUE_NORMAL = 'java';

    /** @var string Name of the worker queue with high priority (e.g. model solutions). */
    public const QUEUE_HIGH = 'javaHigh';

    /** @var string Name of the worker queue with low priority (e.g. batch reruns). */
    public const QUEUE_LOW = 'javaLow';

    /** @var string Name of the worker queue without a Java environment and high priority. */
    public const QUEUE_NON_JAVA_HIGH = 'defaultHigh';

    /** @var string Name of the worker queue without a Java environment (the default queue). */
    public const QUEUE_NON_JAVA = 'default';

    /** @var string Name of the worker queue without a Java environment and low priority (e.g. checksums). */
    public const QUEUE_NON_JAVA_LOW = 'defaultLow';
    const STORAGE_PATH_SUBMISSIONS = 'submissions';

    protected $attributes = [
        'sha256' => self::SHA256_PENDING,
        'status' => self::STATUS_PENDING,
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function (ZipSubmission $submission) {
            // You may not delete files which are relied upon by assessments
            if ($submission->modelSolution || $submission->assessment) {
                return false;
            }

            // When deleting a submission, delete all its result files
            foreach ($submission->resultFiles()->get() as $rf) {
                $rf->delete();
            }
        });
        static::deleted(function (ZipSubmission $submission) {
            // Delete the referenced ZIP file when the row is deleted
            if ($submission->diskPath) {
                Storage::delete($submission->diskPath);
            }
        });
    }

    /**
     * Returns the files that were produced from this submission.
     * @return HasMany|Collection|BuildResultFile[]
     */
    public function resultFiles() {
        return $this->hasMany('App\BuildResultFile');
    }

    /**
     * @param UploadedFile $jobFile
     * @param int $author_id
     * @param int $submitter_id
     * @return ZipSubmission
     */
    public static function createFromUploadedFile(UploadedFile $jobFile, int $author_id, int $submitter_id): ZipSubmission
    {
        $storagePath = $jobFile->store(self::STORAGE_PATH_SUBMISSIONS);
        $submission = new ZipSubmission;
        $submission->filename = $jobFile->getClientOriginalName();
        $submission->diskPath = $storagePath;
        $submission->user_id = $author_id;
        $submission->submitter_user_id = $submitter_id;
        $submission->save();
        return $submission;
    }

    /**
     * Duplicates this submission, creating a new ZipSubmission object that refers to a copy of the submitted ZIP file.
     */
    public function copy(): ZipSubmission {
        // Create a path in storage with a small dummy file then copy the original file to it
        $pathToCopy = Storage::putFile(self::STORAGE_PATH_SUBMISSIONS,
            UploadedFile::fake()->createWithContent('dummy', 'ignoreme'));
        Storage::delete($pathToCopy);
        Storage::copy($this->diskPath, $pathToCopy);

        $newZipSubmission = new ZipSubmission;
        $newZipSubmission->filename = $this->filename;
        $newZipSubmission->diskPath = $pathToCopy;
        $newZipSubmission->user_id = $this->user_id;
        $newZipSubmission->submitter_user_id = $this->submitter_user_id;
        $newZipSubmission->sha256 = $this->sha256;
        $newZipSubmission->save();
        return $newZipSubmission;
    }

    /**
     * @return BuildResultFile|null First result file from the {@link BuildResultFile::SOURCE_STDOUT} source.
     */
    public function stdoutResult(): ?BuildResultFile
    {
        return $this->resultFiles->where('source', BuildResultFile::SOURCE_STDOUT)->first();
    }

    /**
     * Returns the {@link ModelSolution} that this submission is referenced from.
     */
    public function modelSolution(): HasOne {
        return $this->hasOne('App\ModelSolution', 'zip_submission_id');
    }

    /**
     * Returns the {@link AssessmentSubmission} that this submission is for.
     */
    public function assessment(): HasOne {
        return $this->hasOne('App\AssessmentSubmission');
    }

    /**
     * Returns the user that authored this ZIP.
     */
    public function user(): BelongsTo {
        return $this->belongsTo('App\User', 'user_id');
    }

    /**
     * Returns the user that submitted this ZIP.
     */
    public function submitter(): BelongsTo {
        return $this->belongsTo('App\User', 'submitter_user_id');
    }

    /**
     * Returns a string representation of the status of this submission.
     */
    public function statusString() {
        switch ($this->status) {
            case self::STATUS_ABORTED: return __('Aborted');
            case self::STATUS_RUNNING: return __('Running');
            case self::STATUS_PENDING: return __('Pending');
            case self::STATUS_OK: return __('Completed');
            default: return __('Failed (:status)', ['status' => $this->status]);
        }
    }

    /**
     * Saves the ZIP into a temporary file, which will be deleted on shutdown.
     * @throws Exception Could not save the ZIP into its temporary location.
     */
    public function saveToTemporaryFile(): string
    {
        $zipStream = Storage::readStream($this->diskPath);
        if (is_null($zipStream)) {
            throw new Exception("Could not fetch the ZIP from storage");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'submission');
        $handle = fopen($tempPath, 'wb');
        if ($handle === FALSE) {
            throw new Exception("Could not open the temporary file for saving");
        }
        register_shutdown_function(function () use ($tempPath) {
            unlink($tempPath);
        });

        while (!feof($zipStream)) {
            fwrite($handle, fread($zipStream, 2048));
        }
        fclose($handle);
        fclose($zipStream);

        return $tempPath;
    }

    public function getFilePathsInZIP(): array {
        $tmpFile = tempnam(sys_get_temp_dir(), 'subzip');
        try {
            file_put_contents($tmpFile, Storage::get($this->diskPath));

            $archive = new ZipArchive;
            if ($archive->open($tmpFile)) {
                $fileNames = [];
                try {
                    for ($i = 0; $i < $archive->numFiles; $i++) {
                        $name = $archive->getNameIndex($i);
                        if (!str_ends_with($name, '/')) {
                            $fileNames[] = $name;
                        }
                    }
                } finally {
                    $archive->close();
                }
                return $fileNames;
            } else {
                return [];
            }
        } finally {
            unlink($tmpFile);
        }
    }
}
