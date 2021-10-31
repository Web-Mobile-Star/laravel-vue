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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AddModelSolutionToAssessmentSubmissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->foreignId('model_solution_id')->nullable()->constrained('model_solutions');
        });

        $latestSolutions = DB::table('model_solutions')
            ->select('model_solutions.id', 'model_solutions.assessment_id')
            ->joinSub(
                DB::table('model_solutions')
                ->select('assessment_id')
                ->selectRaw('MAX(version) AS max_version')
                ->groupBy('assessment_id'),
                'max_versions',
                function ($join) {
                    $join->on('model_solutions.assessment_id', '=', 'max_versions.assessment_id')
                         ->on('model_solutions.version', '=', 'max_versions.max_version');
                }
            )->get();

        foreach ($latestSolutions as $solution) {
            DB::table('assessment_submissions')
                ->where('assessment_id', $solution->assessment_id)
                ->update(['model_solution_id' => $solution->id]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->dropForeign('assessment_submissions_model_solution_id_foreign');
            $table->dropColumn('model_solution_id');
        });
    }
}
