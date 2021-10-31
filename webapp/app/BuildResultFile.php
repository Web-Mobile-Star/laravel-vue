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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

/**
 * @property int id Unique auto-incremented ID.
 * @property int zip_submission_id Foreign key to the {@link ZipSubmission} that originated this result.
 * @property string source Short name of the source (e.g. stdout, stderr, junit, jacoco, jacoco-it, pit).
 * @property bool gzipped Were the file contents gzipped?
 * @property string originalPath Original path within the source (used to serve raw HTML reports).
 * @property string diskPath Path within the Laravel disk.
 * @property string mimeType MIME type to be used to serve the content.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 *
 * @property ZipSubmission submission Submission that produced this file.
 */
class BuildResultFile extends Model
{
    use HasFactory;

    /** @var string The build result file is the combined stderr+stdout of the build process. */
    const SOURCE_STDOUT = 'stdout';

    /** @var string The build result file was produced by an automated unit testing framework in JUnit XML format. */
    const SOURCE_JUNIT = 'junit';

    public static function boot()
    {
        parent::boot();

        static::deleted(function ($brf) {
            // Delete the referenced file when the row is deleted
            if ($brf->diskPath && Storage::exists($brf->diskPath)) {
                Storage::delete($brf->diskPath);

                $folder = dirname($brf->diskPath);
                $files = Storage::files($folder);
                if (sizeof($files) == 0) {
                    Storage::deleteDirectory($folder);
                }
            }
        });
    }

    /**
     * @param string $filePath
     * @return string
     */
    private static function getMIMEType(string $filePath): string
    {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'txt': return 'text/plain';
            case 'js':  return 'text/javascript';
            case 'css': return 'text/css';
        }
        return mime_content_type($filePath);
    }

    /**
     * Return the submission that produced this result.
     * @return BelongsTo
     */
    public function submission() {
        return $this->belongsTo('App\ZipSubmission', 'zip_submission_id');
    }

    /**
     * Creates a new instance of this model.
     * @param ZipSubmission $submission Submission which produced this result file.
     * @param string $source Name of the source that produced this file (e.g. stdout, stderr,
     *                       junit, jacoco, jacoco-it, pit).
     * @param string $filePath Path to the file.
     * @param string $sourceRoot Root folder for the source.
     * @return BuildResultFile
     */
    public static function createFrom(ZipSubmission $submission, string $source,
                                      string $filePath, string $sourceRoot): BuildResultFile
    {
        $brf = new BuildResultFile;

        $brf->zip_submission_id = $submission->id;
        $brf->source = $source;
        $brf->mimeType = self::getMIMEType($filePath);
        if (str_starts_with($filePath, $sourceRoot)) {
            $brf->originalPath = substr($filePath, strlen($sourceRoot));
        } else {
            $brf->originalPath = $filePath;
        }

        // We use the SHA1 checksum + extension as the filename, as we may have the same file
        // coming from multiple sources (e.g. jacoco and jacoco-it). This works fine as long
        // as we do not delete individual BuildResultFile, and rather just delete entire
        // ZipSubmission objects and let it cascade to all their BuildResultFiles.
        $brf->gzipped = self::isCompressible($brf->mimeType);
        if ($brf->gzipped) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'afgz');
            copy($filePath, "compress.zlib://" . $tmpFile . ".gz");
            $filePath = $tmpFile . ".gz";
            register_shutdown_function(function() use ($tmpFile) {
                unlink($tmpFile);
            });
        }
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        $brf->diskPath = Storage::putFileAs('results/s' . $submission->id, new File($filePath),
            sha1_file($filePath) . "." . $fileExtension);

        $brf->save();
        return $brf;
    }

    /**
     * Returns true if this mime type is compressible (usually text-based).
     *
     * @param string $mime MIME type to be checked.
     * @return bool
     */
    public static function isCompressible($mime): bool
    {
        return str_starts_with($mime, 'text/') || str_ends_with($mime, ' /xml');
    }

    /**
     * Returns the original path of this file within its source, without the initial slash.
     */
    public function pathWithoutInitialSlash() {
        $path = $this->originalPath;
        if (str_starts_with($path, '/')) {
            return substr($path, 1);
        }
        return $path;
    }

    /**
     * Returns the URL to view this file.
     *
     * @param ZipSubmission $submission Submission that this belongs to (can use {@link BuildResultFile::submission()}).
     */
    public function url(ZipSubmission $submission): string
    {
        return route('jobs.showResult', [
            'job' => $submission->id,
            'source' => $this->source,
            'path' => $this->pathWithoutInitialSlash()
        ]);
    }

    /**
     * Streams the contents of the file (uncompressing it if needed) into a temporary file,
     * which will be deleted upon PHP shutdown.
     */
    public function unpackIntoTemporaryFile() {
        $fileStream = Storage::readStream($this->diskPath);
        if ($this->gzipped) {
            // From https://bugs.php.net/bug.php?id=68556
            //
            // The zlib.inflate filter takes a "window" parameter, which is the base-2 log of the history buffer size,
            // so that 2^$W bytes are allocated by the decompressor. The value must be greater or equal to the value
            // used for decompression: we'll just use the highest one (15) to be safe.
            //
            // In addition, we add 16 so we use the GZIP format instead of the ZLIB format. That means we must use a
            // window of 15|16=31.
            //
            // It is supposed to mimic the behaviour in the zlib library: http://www.zlib.net/manual.html#Advanced
            stream_filter_append($fileStream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 15|16]);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'afjunit');
        $tempFileStream = fopen($tempFile, 'w+b');
        while (!feof($fileStream)) {
            fwrite($tempFileStream, fread($fileStream, 2048));
        }
        fclose($fileStream);
        fclose($tempFileStream);

        register_shutdown_function(function() use ($tempFile) {
            unlink($tempFile);
        });
        return $tempFile;
    }

    protected $fillable = [
        'zip_submission_id',
        'source',
        'gzipped',
        'originalPath',
        'mimeType',
        'diskPath',
    ];
}
