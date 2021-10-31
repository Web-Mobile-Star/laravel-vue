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

namespace App\Http\Controllers;

use App\AssessmentSubmission;
use App\TeachingModule;
use App\ZipSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Controller for managing a single submission on AutoFeedback.
 */
class AssessmentSubmissionController extends Controller
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Displays the chosen submission, as seen by the student.
     *
     * @param TeachingModule $module
     * @param AssessmentSubmission $submission
     */
    public function show(Request $request, TeachingModule $module, AssessmentSubmission $submission) {
        $this->authorize('view', $submission);
        $this->authorizeNesting($module, $submission);

        return view ('submissions.show', [
            'module' => $module,
            'submission' => $submission,
            'byClass' => $request->get('byClass') ?? false,
        ]);
    }

    /**
     * Deletes the chosen submission and all of its files.
     * @param TeachingModule $module
     * @param AssessmentSubmission $submission
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(TeachingModule $module, AssessmentSubmission $submission) {
        $this->authorize('delete', $submission);
        $this->authorizeNesting($module, $submission);
        $submission->delete();

        $showURL = route('modules.submissions.show', [
            'module' => $module->id,
            'submission' => $submission->id
        ]);

        if (URL::previous() == $showURL) {
            // Referer came from the same URL that was viewing it, so we cannot just redirect there (it will 404)
            $redirectURL = route( 'modules.assessments.showSubmissions', [
                'module' => $module->id,
                'assessment' => $submission->assessment->id,
            ]);
        } else {
            $redirectURL = URL::previous();
        }

        return redirect($redirectURL)->with('status', __('Submission deleted successfully.'));
    }

    /**
     * Reruns the chosen submission, replacing all the result files and marks.
     * @param TeachingModule $module
     * @param AssessmentSubmission $submission
     */
    public function rerun(TeachingModule $module, AssessmentSubmission $submission) {
        $this->authorize('rerun', $submission);
        $this->authorizeNesting($module, $submission);
        $newSubmission = $submission->rerun();

        return redirect(route('modules.submissions.show', [
            'module' => $module->id,
            'submission' => $newSubmission->id,
        ]))->with(
            'status',
            __('Scheduled re-run and re-marking of submission :id.', ['id' => $submission->id]));
    }

    /**
     * @param TeachingModule $module
     * @param AssessmentSubmission $submission
     * @throws AuthorizationException
     */
    private function authorizeNesting(TeachingModule $module, AssessmentSubmission $submission): void
    {
        if ($submission->assessment->usage->teaching_module_id != $module->id) {
            throw new AuthorizationException("The assessment must belong to the specified module.");
        }
    }

}
