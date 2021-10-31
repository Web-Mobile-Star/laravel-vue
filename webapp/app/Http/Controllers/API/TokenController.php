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

use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    /**
     * Creates a Sanctum code submission token for the authenticated user, using the request IP for its name.
     * @param Request $request
     * @return string
     */
    public function createToken(Request $request) {
        $request->validate([
            'tokenName' => 'required',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken($request->get('tokenName'));
        $expirationString = Carbon::now()->addMinutes(config('sanctum.expiration'))->toISOString();

        return response()->json([
            'token' => $token->plainTextToken,
            'expiration' => $expirationString,
        ]);
    }

    /**
     * Validates a token.
     */
    public function validateToken() {
        return response()->json(['valid' => true]);
    }
}
