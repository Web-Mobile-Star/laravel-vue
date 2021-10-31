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

use App\Jobs\CSVImportJob;
use App\Policies\TeachingModuleUserPolicy;
use App\Rules\CSVHasColumn;
use App\Rules\EmailIsNotEnrolledIn;
use App\Rules\HasFieldEqualTo;
use App\Rules\IsTeachingModelUserRole;
use App\TeachingModule;
use App\TeachingModuleUser;
use App\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use League\Csv\Exception;
use League\Csv\Reader;
use Spatie\Permission\Models\Role;

class TeachingModuleUserController extends Controller
{
    /** @var int Maximum size for imported CSVs, in kilobytes. */
    const MAX_FILE_SIZE_KB = 100;

    /** @var string Name of the column that lists the usernames to be imported. */
    const CSV_USERS_COLUMN = 'Username';

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @param TeachingModule $module Module whose users should be listed.
     * @return View
     * @throws AuthorizationException
     */
    public function index(TeachingModule $module)
    {
        $this->authorize('viewUsers', $module);
        $users = $module->users()
            ->select('teaching_module_users.*')
            ->with(['user', 'roles'])
            ->join('users', 'users.id', '=', 'teaching_module_users.user_id')
            ->orderBy('users.name')
            ->paginate(SubmissionsTable::ITEMS_PER_PAGE);

        return view('modules.users.index', [
            'module' => $module, 'users' => $users
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param TeachingModule $module
     * @return View
     */
    public function create(TeachingModule $module)
    {
        $this->authorize('enrolUsers', $module);
        return view('modules.users.create', [
            'module' => $module,
            'moduleUser' => new TeachingModuleUser,
            'roles' => TeachingModuleUserPolicy::allRoles()->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @return RedirectResponse
     */
    public function store(Request $request, TeachingModule $module)
    {
        $this->authorize('enrolUsers', $module);

        $request->validate([
            'email' => ['required', 'email', 'exists:users,email', new EmailIsNotEnrolledIn($module)],
            'roles' => 'required|array|min:1',
            'roles.*' => ['required', 'integer', 'distinct', new IsTeachingModelUserRole]
        ]);

        /** @var User $user */
        $email = $request->get('email');
        $user = User::where('email', $email)->firstOrFail();

        // Create the user + module pair
        /** @var TeachingModuleUser $tmu */
        $tmu = TeachingModuleUser::create([
            'user_id' => $user->id,
            'teaching_module_id' => $module->id
        ]);

        // Sync the selected roles
        $roles = Role::whereIn('id', $request->get('roles'))->pluck('name')->toArray();
        $tmu->syncRoles($roles);
        $roleNames = array_map(function ($e) { return TeachingModuleUserPolicy::cleanRoleName($e); }, $roles);

        return redirect()
            ->route('modules.users.index', $module->id)
            ->with('status', __('Enrolled :email with role(s): :roleNames', [
                'email' => $email, 'roleNames' => implode(', ', $roleNames)
            ]));
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @param TeachingModuleUser $user
     * @return View
     */
    public function show(Request $request, TeachingModule $module, TeachingModuleUser $user): View
    {
        $this->authorize('view', $user);
        $this->authorizeNesting($user, $module);

        $submissions = null;
        if ($request->user()->can('viewSubmissions', $user)) {
            $submissions = SubmissionsTable::forAuthor($request, $module, $user);
        }

        return view('modules.users.show', [
            'module' => $module,
            'moduleUser' => $user,
            'submissions' => $submissions,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param TeachingModule $module
     * @param TeachingModuleUser $user
     * @return View
     * @throws AuthorizationException
     */
    public function edit(TeachingModule $module, TeachingModuleUser $user)
    {
        $this->authorize('update', $user);
        $this->authorizeNesting($user, $module);

        return view('modules.users.edit', [
            'module' => $module,
            'moduleUser' => $user,
            'roles' => TeachingModuleUserPolicy::allRoles()->get()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @param TeachingModuleUser $user
     * @return RedirectResponse
     */
    public function update(Request $request, TeachingModule $module, TeachingModuleUser $user)
    {
        $this->authorize('update', $user);
        $this->authorizeNesting($user, $module);

        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => ['required', 'integer', 'distinct', new IsTeachingModelUserRole]
        ]);

        // Sync the selected roles
        $roles = Role::whereIn('id', $request->get('roles'))->pluck('name')->toArray();
        $user->syncRoles($roles);
        $roleNames = array_map(function ($e) { return TeachingModuleUserPolicy::cleanRoleName($e); }, $roles);

        return redirect()
            ->route('modules.users.show', ['module' => $module->id, 'user' => $user->id])
            ->with('status', __('Set roles of :name to: :roles.', [
                'name' => $user->user->name,
                'roles' => implode(', ', $roleNames)
            ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TeachingModule $module
     * @param TeachingModuleUser $user
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function destroy(TeachingModule $module, TeachingModuleUser $user)
    {
        $this->authorize('delete', $user);
        $this->authorizeNesting($user, $module);

        $name = $user->user->name;
        $user->delete();

        return redirect()
            ->route('modules.users.index', $module->id)
            ->with('status', __('User ":name" has been removed from the module successfully.', ['name' => $name]));
    }

    /**
     * Remove the specified users from the module.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @return RedirectResponse
     */
    public function destroyMany(Request $request, TeachingModule $module)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => [
                'required', 'integer',
                new HasFieldEqualTo('teaching_module_users', 'teaching_module_id', $module->id,
                    'The user must belong to the specified module.')
            ]
        ]);

        // Authorize all deletions, then run the deletions
        $ids = $request->get('ids');
        $users = TeachingModuleUser::findOrFail($ids);
        foreach ($users as $e) {
            $this->authorize('delete', $e);
        }
        foreach ($users as $e) {
            $e->delete();
        }

        return redirect()
            ->route('modules.users.index', $module->id)
            ->with('status', __('Removed :count user(s) successfully', ['count' => count($users)]));
    }

    /**
     * Provide subset of candidates whose email starts with a certain prefix.
     * @param Request $request
     * @param TeachingModule $module
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function autocomplete(Request $request, TeachingModule $module) {
        $this->authorize('enrolUsers', $module);

        $prefix = $request->get('prefix');
        if (is_null($prefix)) {
            return response()->json(['error' => __('Prefix is missing')], 400);
        }

        // Remove special characters from query
        $prefix = str_replace('%', '', $prefix);
        $prefix = str_replace('_', '', $prefix);
        $prefix = trim($prefix);

        $users = User::where('email', 'like', $prefix . '%')
            ->limit(10)
            ->get(['email'])
            ->toArray();

        $emails = array_map(function ($u) { return $u['email']; }, $users);
        return response()->json($emails);
    }

    /**
     * Shows a form for importing users from Blackboard.
     * @param TeachingModule $module
     * @return Application|Factory|View
     * @throws AuthorizationException
     */
    public function importForm(TeachingModule $module) {
        $this->authorize('enrolUsers', $module);
        return view('modules.users.import', ['module' => $module]);
    }

    /**
     * Validates the import request and submits a background job to do the import process.
     */
    public function import(Request $request, TeachingModule $module) {
        $this->authorize('enrolUsers', $module);

        $request->validate([
            'csvfile' => ['required', 'file', 'mimes:csv,txt',
                'max:'.self::MAX_FILE_SIZE_KB,
                new CSVHasColumn(self::CSV_USERS_COLUMN)]
        ]);

        $reader = Reader::createFromPath($request->file('csvfile')->getRealPath(), 'r');
        try {
            $reader->setHeaderOffset(0);
            $header = $reader->getHeader();

            $usernames = [];
            foreach ($reader->getRecords() as $offset => $record) {
                $usernames[] = $record[self::CSV_USERS_COLUMN];
            }

            CSVImportJob::dispatch($module, $usernames);
            return redirect()
                ->route('modules.users.index', $module->id)
                ->with('status', __('CSV file parsed correctly. Running import in the background: it may take a while.'));

        } catch (Exception $e) {
            Log::error($e);
            return redirect()
                ->route('modules.users.importForm', $module->id)
                ->with('warning', __('There was a problem importing the CSV file. Please consult the administrator.'));
        }
    }

    /**
     * @param TeachingModuleUser $user
     * @param TeachingModule $module
     * @throws AuthorizationException
     */
    private function authorizeNesting(TeachingModuleUser $user, TeachingModule $module): void
    {
        if ($user->teaching_module_id !== $module->id) {
            throw new AuthorizationException('The user must belong to the module.');
        }
    }
}
