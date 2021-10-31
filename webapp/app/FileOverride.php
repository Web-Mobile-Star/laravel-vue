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

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id Unique auto-incremented ID.
 * @property int assessment_id Foreign key to the {@link Assessment} that this override is for.
 * @property string path File path from the model solution to be extracted on top of the students' submission.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 *
 * @property Assessment assessment Assessment that this override is for.
 */
class FileOverride extends Model
{
    protected $fillable = [
        'assessment_id', 'path',
    ];

    public function assessment(): BelongsTo {
        return $this->belongsTo('App\Assessment');
    }

}
