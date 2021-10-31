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

namespace App\Http\Controllers;

use App\Assessment;
use App\Policies\AssessmentPolicy;
use App\Rules\CSVHasColumn;
use App\TeachingModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use League\Csv\Exception;
use League\Csv\Reader;

class AssessmentSubmissionComparisonController extends Controller
{
    const MAX_FILE_SIZE_KB = 1000;

    const FILENAME_COLUMN = 'filename';
    const SHA256_COLUMN = 'sha256';
    const EMAIL_COLUMN = 'email';

    const VP_DIFFERENT_EXTERNAL = 'differentExternal';
    const VP_NOT_IN_EXTERNAL = 'notInExternal';
    const VP_NOT_IN_MODULE = 'notInModule';

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Shows the upload form to provide the CSV file that will drive the comparison.
     * @param TeachingModule $module
     * @param Assessment $assessment
     * @return View
     * @throws AuthorizationException
     */
    public function showCSVForm(TeachingModule $module, Assessment $assessment): View
    {
        $this->authorize('compare', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        return view('assessments.compare.uploadCSV', [
            'module' => $module,
            'assessment' => $assessment,
            'columns' => [
                self::FILENAME_COLUMN => __('Filename of the submitted file.'),
                self::SHA256_COLUMN => __('SHA-256 checksum of the submitted file.'),
                self::EMAIL_COLUMN => __('Email of the author (must match that in AutoFeedback).'),
            ]
        ]);
    }

    public function processCSV(Request $request, TeachingModule $module, Assessment $assessment) {
        $this->authorize('compare', $assessment);
        AssessmentPolicy::authorizeNesting($module, $assessment);

        $request->validate([
            'csvfile' => ['required', 'file', 'mimes:csv,txt',
                'max:'.self::MAX_FILE_SIZE_KB,
                new CSVHasColumn(self::EMAIL_COLUMN),
                new CSVHasColumn(self::SHA256_COLUMN),
                new CSVHasColumn(self::FILENAME_COLUMN)]
        ]);

        $reader = Reader::createFromPath($request->file('csvfile')->getRealPath(), 'r');
        try {
            $reader->setHeaderOffset(0);
            $submissionsByEmail = [];
            foreach ($reader->getRecords() as $offset => $record) {
                $submissionsByEmail[$record[self::EMAIL_COLUMN]] = [
                    Assessment::COMPARE_FILENAME => $record[self::FILENAME_COLUMN],
                    Assessment::COMPARE_SHA256_EXTERNAL => $record[self::SHA256_COLUMN],
                ];
            }

            $assessment->compareSubmissions($submissionsByEmail,
                $differentExternal, $notInExternal, $notInModule);

            return view('assessments.compare.report', [
                'module' => $module,
                'assessment' => $assessment,
                self::VP_DIFFERENT_EXTERNAL => $differentExternal,
                self::VP_NOT_IN_EXTERNAL => $notInExternal,
                self::VP_NOT_IN_MODULE => $notInModule,
            ]);

        } catch (Exception $e) {
            Log::error($e);
            return redirect(URL::previous())
                ->with('warning', __('There was a problem reading the CSV file. Please consult the administrator.'));
        }
    }

}
