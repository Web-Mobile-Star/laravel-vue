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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpandItemDescription extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('teaching_module_items', function (Blueprint $table) {
            $table->text('description_markdown2')->default('');
        });
        $this->copyColumnValues();
        $this->dropAndRename();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('teaching_module_items', function (Blueprint $table) {
            $table->char('description_markdown2')->default('');
        });
        $this->copyColumnValues();
        $this->dropAndRename();
    }

    private function copyColumnValues(): void
    {
        DB::table('teaching_module_items')
            ->update(['description_markdown2' => DB::raw('description_markdown')]);
    }

    private function dropAndRename(): void
    {
        Schema::table('teaching_module_items', function (Blueprint $table) {
            $table->dropColumn('description_markdown');
            $table->renameColumn('description_markdown2', 'description_markdown');
        });
    }
}
