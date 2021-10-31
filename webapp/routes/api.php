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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth.basic.once')
    ->post('tokens/create', 'API\TokenController@createToken')
    ->name('api.tokens.create');

Route::middleware('auth:sanctum')
    ->post('tokens/validate', 'API\TokenController@validateToken')
    ->name('api.tokens.validate');

Route::post('assessments/{assessment}/storeSubmission', 'API\AssessmentController@storeSubmission')
    ->name('api.assessments.storeSubmission');
