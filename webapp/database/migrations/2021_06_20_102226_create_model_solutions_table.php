<?php

/**
 * Copyright 2021 Aston University
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateModelSolutionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('model_solutions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('assessment_id')->constrained('assessments');
            $table->foreignId('zip_submission_id')->unique()->constrained('zip_submissions');
            $table->unsignedInteger('version');
            $table->unique(['assessment_id', 'version']);
        });

        // Copy over the data from the old assessments table
        $select = DB::table('assessments')
            ->select(['id', 'model_zip_submission_id', 'created_at', 'updated_at'])
            ->selectRaw('1 AS version')
            ->whereNotNull('model_zip_submission_id');
        DB::table('model_solutions')
            ->insertUsing(['assessment_id', 'zip_submission_id', 'created_at', 'updated_at', 'version'], $select);

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign('assessments_model_zip_submission_id_foreign');
            $table->dropColumn('model_zip_submission_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assessments', function (Blueprint $table) {
           $table->foreignId('model_zip_submission_id')->nullable()->unique()->constrained('zip_submissions');
        });

        $latestZipSubmissions = DB::table('model_solutions')
            ->select(['model_solutions.assessment_id', 'model_solutions.zip_submission_id'])
            ->joinSub(
                DB::table('model_solutions')
                  ->select('assessment_id')
                  ->selectRaw('MAX(version) as max_version')
                  ->groupBy('assessment_id'),
                'latest_model_solutions',
                function ($join) {
                    $join->on('latest_model_solutions.assessment_id', '=', 'model_solutions.assessment_id')
                         ->on('latest_model_solutions.max_version', '=', 'model_solutions.version');
                }
            );
        foreach ($latestZipSubmissions->get() as $row) {
            DB::table('assessments')
                ->where('id', $row->assessment_id)
                ->update(['model_zip_submission_id' => $row->zip_submission_id]);
        }

        Schema::dropIfExists('model_solutions');
    }
}
