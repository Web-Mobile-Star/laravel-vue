<?php

/**
 *  Copyright 2021 Aston University
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

/**
 * Joins the passed_feedback_markdown and failed_feedback_markdown fields into one, now that
 * we have the af_when_passed and af_when_failed conditional blocks.
 */
class UnifyFeedbackTemplate extends Migration
{
    const TABLE = 'assessment_tests';
    const BACKTICKS = '``````';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->renameColumn('failed_feedback_markdown', 'feedback_markdown');
        });

        $tests = DB::table(self::TABLE)
            ->select(['id', 'passed_feedback_markdown', 'feedback_markdown']);

        foreach ($tests->get() as $row) {
            $feedback = '';
            if ($row->passed_feedback_markdown) {
                $feedback = self::BACKTICKS . \App\Markdown\ConditionalBlockRenderer::INFOLINE_PASSED
                    . "\n" . $row->passed_feedback_markdown
                    . "\n" . self::BACKTICKS . "\n";
            }
            if ($row->feedback_markdown) {
                $feedback = "``````" . \App\Markdown\ConditionalBlockRenderer::INFOLINE_FAILED
                    . "\n" . $row->feedback_markdown
                    . "\n" . self::BACKTICKS . "\n";
            }

            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->update(['feedback_markdown' => $feedback]);
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
           $table->dropColumn('passed_feedback_markdown');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assessment_tests', function (Blueprint $table) {
            $table->renameColumn('feedback_markdown', 'failed_feedback_markdown');
            $table->text('passed_feedback_markdown');
        });

        // Duplicate the template over both failed/passed options
        DB::table(self::TABLE)
            ->update(['passed_feedback_markdown' => DB::raw('failed_feedback_markdown')]);
    }
}
