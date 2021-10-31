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

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a version of the model solution for an assessment.
 *
 * @property int id Unique auto-incremented ID.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property int assessment_id ID of the {@link Assessment} that this is a model solution for.
 * @property int zip_submission_id ID of the {@link ZipSubmission} that was submitted.
 * @property int version Version number for this model solution (starting at 1).
 *
 * @property Assessment assessment Assessment that this submission is for.
 * @property ZipSubmission submission File that was submitted.
 */
class ModelSolution extends Model
{
    use HasFactory;

    protected $attributes = [
      'version' => 1
    ];

    protected $fillable = [
        'version'
    ];

    public function assessment(): BelongsTo {
        return $this->belongsTo('App\Assessment');
    }

    public function submission(): BelongsTo {
        return $this->belongsTo('App\ZipSubmission', 'zip_submission_id');
    }

    public function assessmentSubmissions(): HasMany {
        return $this->hasMany('App\AssessmentSubmission', 'model_solution_id');
    }

    /**
     * Returns true iff this is the latest version of the model solution for its assessment.
     */
    public function isLatest(): bool
    {
        return $this->assessment->latestModelSolution->id == $this->id;
    }
}
