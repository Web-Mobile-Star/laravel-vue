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

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Enables admin mode.
     */
    public function enable() {
        return $this->setAdminMode(true);
    }

    /**
     * Disables admin mode.
     */
    public function disable() {
        return $this->setAdminMode(false);
    }

    /**
     * Toggles the admin flag.
     */
    private function setAdminMode($enabled) {
        if (!Auth::user()->hasRole(User::SUPER_ADMIN_ROLE)) {
            Log::warning('User ' . Auth::id() . ' tried to switch admin mode to ' . $enabled);
            return response('', 403);
        }

        User::setAdminMode($enabled);
        return redirect()
            ->route('home')
            ->with('status', __($enabled
                ? 'Admin mode enabled. Remember: with great power, comes great responsibility!'
                : 'Admin mode disabled.'));
    }

}
