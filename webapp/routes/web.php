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

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes(['register' => false, 'reset' => false, 'password.request' => false]);

Route::get('/', 'HomeController@index')->name('home');

// TOKEN MANAGEMENT

Route::get('/tokens', 'Auth\TokenManagementController@show')->name('tokens.show');
Route::delete('/tokens/{token}', 'Auth\TokenManagementController@destroy')->name('tokens.destroy');

// ADMIN MODE

Route::get('/admin/enable', 'AdminController@enable');
Route::post('/admin/enable', 'AdminController@enable')->name('enableAdmin');
Route::get('/admin/disable', 'AdminController@disable');
Route::post('/admin/disable', 'AdminController@disable')->name('disableAdmin');

// JOBS

Route::resource('jobs', 'JobController')->only([
    'index', 'show', 'destroy', 'store', 'create'
]);

Route::get('/jobs/{job}/results/{source}/{path}', 'JobController@showResult')
    ->name('jobs.showResult')
    ->where('path', '.*');

Route::get('/jobs/{job}/download', 'JobController@download')
    ->name('jobs.download');

Route::delete('/jobs', 'JobController@destroyMany')
    ->name('jobs.destroyMany');

// MODULES

Route::resource('modules', 'TeachingModuleController');

Route::post('/modules/{module}/switchRole', 'TeachingModuleController@switchRole')
    ->name('modules.switchRole');

Route::get('/modules/{module}/users/autocomplete', 'TeachingModuleUserController@autocomplete')
    ->name('modules.users.autocomplete');

Route::delete('/modules/{module}/users', 'TeachingModuleUserController@destroyMany')
    ->name('modules.users.destroyMany');

Route::get('/modules/{module}/users/import', 'TeachingModuleUserController@importForm')
    ->name('modules.users.importForm');
Route::post('/modules/{module}/users/import', 'TeachingModuleUserController@import')
    ->name('modules.users.import');

Route::resource('modules.users', 'TeachingModuleUserController');

Route::resource('modules.items', 'TeachingModuleItemController')->except(['index']);

// MODEL SOLUTION

Route::get(
    '/modules/{module}/assessments/{assessment}/solution',
    'AssessmentController@showModelSolution'
)->name('modules.assessments.showModelSolution');

Route::post(
    '/modules/{module}/assessments/{assessment}/solution',
    'AssessmentController@storeModelSolution'
)->name('modules.assessments.storeModelSolution');

Route::delete(
    '/modules/{module}/assessments/{assessment}/solution',
    'AssessmentController@destroyModelSolution'
)->name('modules.assessments.destroyModelSolution');

// FILE OVERRIDES

Route::get(
    '/modules/{module}/assessments/{assessment}/overrides',
    'AssessmentController@showOverrides'
)->name('modules.assessments.showOverrides');

Route::post(
    '/modules/{module}/assessments/{assessment}/overrides',
    'AssessmentController@storeOverrides'
)->name('modules.assessments.storeOverrides');

// SUBMISSIONS TABLE

Route::get(
    '/modules/{module}/assessments/{assessment}/submissions',
    'AssessmentController@showSubmissions'
)->name('modules.assessments.showSubmissions');

Route::post(
    '/modules/{module}/assessments/{assessment}/submissions/rerun',
    'AssessmentController@rerunSubmissions'
)->name('modules.assessments.rerunSubmissions');

Route::get(
    '/modules/{module}/assessments/{assessment}/submissions/csv',
    'AssessmentController@downloadSubmissionsCSV'
)->name('modules.assessments.downloadSubmissionsCSV');

// SUBMISSIONS COMPARISON

Route::get(
    '/modules/{module}/assessments/{assessment}/submissions/compare',
    'AssessmentSubmissionComparisonController@showCSVForm'
)->name('modules.assessments.compare.showCSVForm');

Route::post(
    '/modules/{module}/assessments/{assessment}/submissions/compare',
    'AssessmentSubmissionComparisonController@processCSV'
)->name('modules.assessments.compare.processCSV');

// ASSESSMENT TESTS

Route::get(
    '/modules/{module}/assessments/{assessment}/tests',
    'AssessmentController@showTests'
)->name('modules.assessments.showTests');

Route::get(
    '/modules/{module}/assessments/{assessment}/tests/edit',
    'AssessmentController@editTests'
)->name('modules.assessments.editTests');

Route::post(
    '/modules/{module}/assessments/{assessment}/tests',
    'AssessmentController@storeTests'
)->name('modules.assessments.storeTests');

// PROGRESS CHART

Route::get(
    '/modules/{module}/assessments/{assessment}/progress/chart',
    'AssessmentController@viewProgressChart'
)->name('modules.assessments.viewProgressChart');

Route::get(
    '/modules/{module}/assessments/{assessment}/progress/stats',
    'AssessmentController@jsonProgressStats'
)->name('modules.assessments.jsonProgressChart');

Route::get(
    '/modules/{module}/assessments/{assessment}/progress/entries/{className}/{testName}/{status}',
    'AssessmentController@jsonClassTestStatus'
)->name('modules.assessments.jsonClassTestStatus');

// SINGLE SUBMISSION MANAGEMENT

Route::post(
    '/modules/{module}/assessments/{assessment}/storeSubmission',
    'AssessmentController@storeSubmission'
)->name('modules.assessments.storeSubmission');

Route::get(
    '/modules/{module}/submissions/{submission}',
    'AssessmentSubmissionController@show'
)->name('modules.submissions.show');

Route::post(
    '/modules/{module}/submissions/{submission}/rerun',
    'AssessmentSubmissionController@rerun'
)->name('modules.submissions.rerun');

Route::delete(
    '/modules/{module}/submissions/{submission}',
    'AssessmentSubmissionController@destroy'
)->name('modules.submissions.destroy');

// HELP PAGES

Route::view(
    '/help/markdown/conditionals',
    'help.markdown_conditionals'
)->name('help.markdown.conditionals');
