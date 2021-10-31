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

use App\BuildResultFile;
use App\Jobs\CalculateChecksumJob;
use App\Jobs\MavenBuildJob;
use App\Rules\HasOnePOM;
use App\ZipSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class JobController extends Controller
{

    /** @var int Number of jobs to list per page. */
    const ITEMS_PER_PAGE = 25;

    /**
     * JobController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List all the jobs.
     */
    public function index(Request $request) {
        $this->authorize('viewAny', ZipSubmission::class);
        $request->validate([
            'page' => 'sometimes|integer|min:1',
        ]);

        $jobs = ZipSubmission::with('user')->paginate(self::ITEMS_PER_PAGE);
        return view('jobs.index', ['jobs' => $jobs, 'page' => $request->get('page', 1)]);
    }

    /**
     * Show the job submission form.
     *
     * @return Application|Factory|Response|View
     */
    public function create() {
        $this->authorize('create', ZipSubmission::class);
        return view('jobs.create');
    }

    /**
     * Send the zipped build to the jobs queue.
     *
     * @param Request $request POST request with the uploaded file.
     * @return RedirectResponse|Response
     */
    public function store(Request $request) {
        $this->authorize('create', ZipSubmission::class);
        $request->validate([
            'jobfile' => ['required','file','mimes:zip','max:'. ZipSubmission::MAX_FILE_SIZE_KB, new HasOnePOM],
        ]);

        $jobFile = $request->file('jobfile');
        $submission = ZipSubmission::createFromUploadedFile($jobFile, Auth::id(), Auth::id());
        MavenBuildJob::withChain([
            new CalculateChecksumJob($submission->id)
        ])->onQueue(ZipSubmission::QUEUE_NORMAL)->dispatch($submission);

        return redirect()->route('jobs.show', $submission);
    }

    /**
     * Shows the details of a particular submission.
     * @param Request $request GET request.
     * @param ZipSubmission $job Submission to show.
     */
    public function show(Request $request, ZipSubmission $job) {
        $this->authorize('view', $job);

        $request->validate([
            /* This is only set by the links from the paged Jobs table: those are the only ones that produce a 'Back'
             * button. For the rest of the links to a job (e.g. from a submission to an assignment), we'll have the
             * user rely on their browser's Back button. */
            'backPage' => 'sometimes|integer|min:1',
        ]);

        $results = $job->resultFiles()->paginate(self::ITEMS_PER_PAGE);
        return view('jobs.show', [
            'job' => $job,
            'results' => $results,
            'backPage' => $request->get('backPage')
        ]);
    }

    /**
     * Shows the contents of a particular results file.
     * @param Request $request GET request.
     * @param ZipSubmission $job Job that produced this file.
     * @param string $source Name of the source that produced the file.
     * @param string $path Path of the file produced by the source.
     * @throws AuthorizationException
     */
    public function showResult(ZipSubmission $job, string $source, string $path) {
        $this->authorize('view', $job);

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        /** @var BuildResultFile $result */
        $result = BuildResultFile::where([
            'zip_submission_id' => $job->id,
            'source' => $source,
            'originalPath' => $path
        ])->firstOrFail();

        $headers = ['Content-Type' => $result->mimeType, 'Content-Disposition' => 'inline'];
        if ($result->gzipped) {
            $headers['Content-Encoding'] = 'gzip';
        }

        return Storage::download($result->diskPath, basename($path), $headers);
    }

    /**
     * Downloads the submission uploaded to the server.
     * @param ZipSubmission $job
     * @return Response
     * @throws AuthorizationException
     */
    public function download(ZipSubmission $job) {
        $this->authorize('view', $job);
        return Storage::download($job->diskPath, $job->filename);
    }

    /**
     * Deletes the chosen submission and all of its files.
     * @param ZipSubmission $job Submission to delete.
     */
    public function destroy(ZipSubmission $job) {
        $this->authorize('delete', $job);
        $job->delete();
        return redirect()
            ->route('jobs.index')
            ->with('status', __('Job :id deleted successfully', ['id' => $job->id ]));
    }

    /**
     * Deletes all the provided jobs.
     * @param Request $request DELETE request with the job IDs.
     */
    public function destroyMany(Request $request) {
        $request->validate([
            'jobIDs' => 'required|array',
            'jobIDs.*' => 'required|integer|exists:zip_submissions,id'
        ]);

        // Authorize all deletions, then run the deletions
        $jobIDs = $request->get('jobIDs');
        $jobs = ZipSubmission::findOrFail($jobIDs);
        foreach ($jobs as $job) {
            $this->authorize('delete', $job);
        }
        foreach ($jobs as $job) {
            $job->delete();
        }

        return redirect()
            ->route('jobs.index')
            ->with('status', __('Deleted :count job(s) successfully', ['count' => count($jobIDs)]));
    }
}
