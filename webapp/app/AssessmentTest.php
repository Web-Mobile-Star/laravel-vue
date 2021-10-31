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

namespace App;

use App\Markdown\ConditionalBlockRenderer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Additional information about a JUnit test in the model solution for this assessment. Used
 * for automated feedback.
 *
 * @property int id Unique identifier for this model.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property string class_name Fully qualified name of the JUnit test suite class.
 * @property string name Name of the test (usually the test method).
 * @property string points Points to be awarded when passing the test (use bcmath to operate on these values).
 * @property string feedback_markdown Markdown source for the feedback to be given (check {@link ConditionalBlockRenderer} for conditional blocks).
 * @property int assessment_id Unique identifier for the {@link Assessment} that this test belongs to.
 * @property string task Name of the task to be used for optional task-centric grouping (instead of a class-centric grouping).
 *
 * @property Assessment assessment Assessment that this test belongs to.
 */
class AssessmentTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_name', 'name', 'points', 'feedback_markdown', 'assessment_id', 'task'
    ];

    public function assessment() {
        return $this->belongsTo('App\Assessment');
    }

}
