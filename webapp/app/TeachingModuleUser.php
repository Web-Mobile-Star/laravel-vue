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

use App\Policies\TeachingModuleUserPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int id Unique ID for this model.
 * @property int user_id ID of the user related to the module.
 * @property int teaching_module_id ID of the teaching module.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 *
 * @property User user User related to the module.
 * @property TeachingModule module Teaching module related to the user.
 * @property Collection submissions Collection of {@link AssessmentSubmission}s related to the user.
 */
class TeachingModuleUser extends Model
{
    use HasFactory;
    use HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'user_id', 'teaching_module_id'
    ];

    public function user(): BelongsTo {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function module(): BelongsTo {
        return $this->belongsTo('App\TeachingModule', 'teaching_module_id' );
    }

    public function submissions(): HasMany {
        return $this->hasMany('App\AssessmentSubmission');
    }

    /**
     * Returns the latest submissions done by this {@link TeachingModuleUser}.
     */
    public function latestSubmissions() {
        return AssessmentSubmission::latest()
            ->where('latest_attempts.teaching_module_user_id', $this->id);
    }

    public function getCleanRoleNames() {
        return array_map(function ($n) {
            return TeachingModuleUserPolicy::cleanRoleName($n);
        }, $this->getRoleNames()->toArray());
    }

}
