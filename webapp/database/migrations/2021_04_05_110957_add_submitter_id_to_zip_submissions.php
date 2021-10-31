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

class AddSubmitterIdToZipSubmissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('zip_submissions', function (Blueprint $table) {
            $table->foreignId('submitter_user_id')->nullable()->constrained('users');
        });

        DB::statement('UPDATE zip_submissions SET submitter_user_id = user_id;');

        Schema::table('zip_submissions', function (Blueprint $table) {
            $table->foreignId('submitter_user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('zip_submissions', function (Blueprint $table) {
            $table->dropForeign('zip_submissions_submitter_user_id_foreign');
            $table->dropColumn('submitter_user_id');
        });
    }
}
