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

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokenManagementController extends Controller
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Shows a list of all the currently available tokens
     */
    public function show() {
        /** @var User $user */
        $user = Auth::user();

        $expirationDates = array_map(function ($t) {
            $createdAt = Carbon::parse($t);
            return $createdAt->addMinutes(config('sanctum.expiration'));
        }, $user->tokens()->pluck('created_at')->toArray());

        return view('tokens.show', [
            'userTokens' => $user->tokens,
            'expirationDates' => $expirationDates,
        ]);
    }

    /**
     * Deletes a specific token.
     * @param Request $request
     * @param PersonalAccessToken $token
     */
    public function destroy(Request $request, PersonalAccessToken $token) {
        $this->authorize('delete', $token);
        $token->delete();
        return redirect(route('tokens.show'))
            ->with('status', __('Token :name revoked successfully.', ['name' => $token->name]));
    }
}
