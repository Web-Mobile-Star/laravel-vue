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
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property integer id Unique ID of the model.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property Collection children {@link TeachingModuleItem}s that are directly inside this folder.
 * @property TeachingModuleItem usage {@link TeachingModuleItem} that uses this folder as its content.
 */
class Folder extends Model
{
    use HasFactory;
    use HasAvailableChildren;
    use Content;

    public static function boot()
    {
        parent::boot();

        static::deleting(function (Folder $folder) {
            // When deleting a submission, delete all its items
            foreach ($folder->children as $c) {
                $c->delete();
            }
        });
    }

    /**
     * @return HasMany with the {@link TeachingModuleItem}s that are directly inside this folder,
     * regardless of availability.
     */
    public function children() {
        return $this->hasMany(TeachingModuleItem::class)->with('module');
    }

    /**
     * @inheritDoc
     */
    public function getIcon(): string {
        return 'fa-folder';
    }
}
