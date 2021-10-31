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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * @property int id The unique ID of the module.
 * @property string name Name of the module.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 *
 * @property Collection|TeachingModuleUser[] users Users enrolled in this module.
 * @property Collection|Assessment[] assessments Assessments within this module.
 * @property Collection|TeachingModuleItem[] availableChildren Collection of {@link TeachingModuleItem} directly contained in this module which are currently available to students.
 * @property Collection|TeachingModuleItem[] children Collection of {@link TeachingModuleItem} directly contained in this module.
 * @property Collection|TeachingModuleItem[] items Collection of {@link TeachingModuleItem} contained in this module, whether directly or indirectly.
 */
class TeachingModule extends Model
{
    use HasFactory;
    use HasAvailableChildren;

    protected $fillable = ['name'];

    public static function boot()
    {
        parent::boot();

        static::deleting(function (TeachingModule  $module) {
            // When deleting a module, delete all the enrolments
            // (NOTE: this does *not* delete the actual users)
            foreach ($module->users as $u) {
                $u->delete();
            }

            // When deleting a submission, delete all its items
            foreach ($module->items as $it) {
                $it->delete();
            }
        });
    }

    /**
     * Returns the users assigned to this module.
     * @return HasMany
     */
    public function users() {
        return $this->hasMany('App\TeachingModuleUser');
    }

    /**
     * Returns the items directly contained within this module, regardless of availability.
     * @return HasMany
     */
    public function children() {
        return $this->items()
            ->where('folder_id', null)
            ->with('module');
    }

    /**
     * Returns all items contained within this module, regardless of availability.
     * @return HasMany
     */
    public function items() {
        return $this->hasMany('App\TeachingModuleItem');
    }

}
