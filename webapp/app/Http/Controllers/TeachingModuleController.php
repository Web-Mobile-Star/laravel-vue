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

use App\Policies\TeachingModulePolicy;
use App\Rules\IsTeachingModelUserRole;
use App\Rules\ModelHasRoleID;
use App\TeachingModule;
use App\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class TeachingModuleController extends Controller
{
    const ITEMS_PER_PAGE = 25;

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
     * @return View
     */
    public function index()
    {
        if (Auth::user()->can('viewAny', TeachingModule::class)) {
            $visibleModules = TeachingModule::orderBy('name');
        } else {
            $visibleModules = TeachingModule::whereIn('id', function ($query) {
                return $query->select('teaching_module_id')
                    ->from('teaching_module_users')
                    ->where('user_id', Auth::id());
            })->orderBy('name');
        }
        $modules = $visibleModules->paginate(self::ITEMS_PER_PAGE);
        return view('modules.index', ['modules' => $modules]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     * @throws AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', TeachingModule::class);
        return view('modules.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', TeachingModule::class);
        $this->validateModuleForm($request);

        $module = new TeachingModule;
        $module->name = $request->get('name');
        $module->save();

        return redirect()
            ->route('modules.index')
            ->with('status', __('Module :name created succesfully', ['name' => $module->name]));
    }

    /**
     * Display the specified resource.
     *
     * @param TeachingModule $module
     * @return View
     * @throws AuthorizationException
     */
    public function show(TeachingModule $module)
    {
        $this->authorize('view', $module);

        /** @var User $user */
        $user = Auth::user();

        return view('modules.show', [
            'module' => $module,
            'moduleUser' => $user->moduleUser($module),
            'items' => $module->childrenVisibleByUser(),
            'activeRole' => TeachingModulePolicy::getActiveRole($user, $module),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param TeachingModule $module
     * @return View
     * @throws AuthorizationException
     */
    public function edit(TeachingModule $module)
    {
        $this->authorize('update', $module);
        return view('modules.create', ['module' => $module]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param TeachingModule $module
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function update(Request $request, TeachingModule $module)
    {
        $this->authorize('update', $module);
        $this->validateModuleForm($request);

        $module->name = $request->get('name');
        $module->save();

        return redirect()
            ->route('modules.show', $module->id)
            ->with('status', __('Module updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TeachingModule $module
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function destroy(TeachingModule $module)
    {
        $this->authorize('delete', $module);
        $module->delete();
        return redirect()
            ->route('modules.index')
            ->with('status', __('Module :name updated successfully', ['name' => $module->name]));
    }

    /**
     * Switches the currently active role in the session for this module.
     * @param Request $request
     * @param TeachingModule $module
     */
    public function switchRole(Request $request, TeachingModule $module) {
        if ($request->get('role') === 'unset') {
            TeachingModulePolicy::setActiveRole($module, null);
        } else {
            /** @var User $user */
            $user = Auth::user();
            $model = $user->moduleUser($module);
            if (is_null($model)) {
                return redirect(URL::previous())
                    ->withErrors(['warning' => __('The authenticated user is not enrolled into this module.')]);
            }

            $request->validate([
                'role' => [
                    'required', 'int',
                    new IsTeachingModelUserRole,
                    new ModelHasRoleID($model)
                ]
            ]);

            /** @var Role $role */
            $role = Role::find($request->get('role'));
            TeachingModulePolicy::setActiveRole($module, $role);
        }

        return redirect()
            ->route('modules.show', $module->id);
    }

    /**
     * @param Request $request
     */
    private function validateModuleForm(Request $request): void
    {
        $request->validate([
            'name' => 'required|string|min:1'
        ]);
    }
}
