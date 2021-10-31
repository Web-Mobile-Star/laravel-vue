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

namespace App\Zip;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Extended version of {@link \ZipArchive} which can recursively zip
 * directory trees.
 *
 * From {@link https://stackoverflow.com/questions/1334613/}, with some
 * added PHPDoc.
 */
class ExtendedZipArchive extends \ZipArchive
{
    /** Member function to add a whole file system subtree to the archive.
     *
     * @param string $dirname Path to the directory.
     * @param string $localname Path within the ZIP where it should be added (by default, at the root).
     * @param array $excludePaths Paths to be excluded from being added.
     */
    public function addTree(string $dirname, string $localname = '', $excludePaths) {
        if ($localname)
            $this->addEmptyDir($localname);
        $this->_addTree($dirname, $localname, $excludePaths);
    }

    /**
     * Extracts a specific entry to the target path. Creates parent folders if needed.
     * @param string $name
     * @param string $targetPath
     * @throws Exception Failed to create the parent folder, or parent folder already exists and it is not a directory.
     * @return bool if the entry with the specified name exists and was correctly unzipped, false otherwise.
     */
    public function extractName(string $name, string $targetPath): bool {
        $oStream = $this->getStream($name);
        if ($oStream) {
            try {
                $parentDirectory = dirname($targetPath);
                if ($parentDirectory && !is_dir($parentDirectory)) {
                    if (file_exists($parentDirectory)) {
                        throw new Exception("Cannot extract $name: $parentDirectory exists but it is not a directory");
                    } elseif (!mkdir($parentDirectory, 0755, true)) {
                        throw new Exception("Could not create parent folder $parentDirectory");
                    }
                }

                $fStream = fopen($targetPath, 'wb');
                while (!feof($oStream)) {
                    fwrite($fStream, fread($oStream, 2048));
                }
                fclose($fStream);

                return true;
            } finally {
                fclose($oStream);
            }
        }
        return false;
    }

    // Internal function, to recurse
    protected function _addTree($dirname, $localName, $excludePaths) {
        $dir = opendir($dirname);
        while ($filename = readdir($dir)) {
            // Discard . and ..
            if ($filename == '.' || $filename == '..')
                continue;

            // Proceed according to type
            $path = $dirname . '/' . $filename;
            $localpath = $localName ? ($localName . '/' . $filename) : $filename;
            if (!in_array($localpath, $excludePaths)) {
                if (is_dir($path)) {
                    // Directory: add & recurse
                    $this->addEmptyDir($localpath);
                    $this->_addTree($path, $localpath, $excludePaths);
                } else if (is_file($path)) {
                    // File: just add
                    $this->addFile($path, $localpath);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Helper class method to zip an entire directory tree in one line.
     * @param string $dirname Path to the directory to be zipped.
     * @param string $zipFilename Path to the ZIP file to be created.
     * @param int $flags Flags to be used in {@link ZipArchive::open()}.
     * @param string $localname Path inside the ZIP file where the zipped directory should be located.
     * @param array $excludePaths Exclude certain paths from being included.
     */
    public static function zipTree(string $dirname, string $zipFilename, int $flags = 0, string $localname = '', array $excludePaths = []) {
        $zip = new self();

        $result = $zip->open($zipFilename, $flags);
        if ($result === TRUE) {
            $zip->addTree($dirname, $localname, $excludePaths);
            if (!$zip->close()) {
                Log::error("Failed to zip tree '$dirname' into '$zipFilename': close returned false");
            }
        } else {
            Log::error("Failed to zip tree '$dirname' into '$zipFilename': open returned $result");
        }
    }

}
