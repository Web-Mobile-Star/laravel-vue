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
use Illuminate\Support\Facades\Schema;

class AddUniqueAttemptToSubmissions extends Migration
{
    const CONSTRAINT_NAME = 'assessment_submissions_unique_assessment_student_attempt';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->unique([
                'teaching_module_user_id',
                'assessment_id',
                'attempt'
            ], self::CONSTRAINT_NAME);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->dropForeign('assessment_submissions_assessment_id_foreign');
            $table->dropForeign('assessment_submissions_teaching_module_user_id_foreign');
            $table->dropUnique(self::CONSTRAINT_NAME);
            $table->foreign('teaching_module_user_id')->references('id')->on('teaching_module_users');
            $table->foreign('assessment_id')->references('id')->on('assessments');
        });
    }
}
