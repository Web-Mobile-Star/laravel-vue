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

namespace App\Http\Controllers\API;

use App\Assessment;
use App\Http\Controllers\Controller;
use App\Rules\EmailIsEnrolledIn;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssessmentController extends Controller
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Performs a submission. Useful for the Maven plugin.
     */
    public function storeSubmission(Request $request, Assessment $assessment) {
        $this->authorize('uploadSubmission', $assessment);
        if ($request->has('authorEmail')) {
            $this->authorize('uploadSubmissionOnBehalfOf', $assessment);
        }

        $moduleItem = $assessment->usage;
        if (is_null($moduleItem)) {
            return response()->json([
                'message' => __('Invalid request.'),
                'errors' => ['internal' => __('Assessment is not associated to a teaching item')]
            ], 400);
        }
        $module = $moduleItem->module;

        $validator = Validator::make($request->all(), [
            'jobfile' =>  \App\Http\Controllers\AssessmentController::submissionValidationRules(),
            'authorEmail' => ['sometimes', 'email', new EmailIsEnrolledIn($module)],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => __('Invalid request.'),
                'errors' => $validator->errors()->toArray()
            ], 400);
        }

        try {
            if ($request->has('authorEmail')) {
                $author = User::where('email', $request->get('authorEmail'))->first();
            } else {
                $author = Auth::user();
            }
            $submitter = Auth::user();

            $asub = \App\Http\Controllers\AssessmentController::processSubmission($module, $assessment, $request, $author, $submitter);
            if ($author->id == $submitter->id) {
                $url = route('modules.items.show', ['module' => $module->id, 'item' => $moduleItem->id]);
            } else {
                $url = route('modules.submissions.show', ['module' => $module->id, 'submission' => $asub->id]);
            }

            return response()->json([
                'message' => __('Attempt :count scheduled for assessment :id', ['count' => $asub->attempt, 'id' => $assessment->id]),
                'url' => $url,
            ]);
        }  catch (\Exception $e) {
            Log::error('Could not replace old submission:\n' . $e);
            return response()->json([
                'message' => __('Could not replace old submission'),
                'errors' => ['internal' => [__('Error replacing submission')]],
            ], 500);
        }
    }
}
