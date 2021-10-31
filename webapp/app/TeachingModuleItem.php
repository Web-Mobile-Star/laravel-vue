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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;

/**
 * @property integer id Unique ID.
 * @property integer teaching_module_id ID of the {@link TeachingModule} that contains this item.
 * @property integer|null folder_id ID of the {@link Folder} that contains this item, or NULL if this item is
 * directly contained by the module.
 * @property string title Title of the item.
 * @property string description_markdown Markdown source code of the description of this item.
 * @property boolean available TRUE iff this item available to students.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 * @property Carbon|null available_from If set, the item is only available to students from this moment.
 * @property Carbon|null available_until If set, the item is only available to students until this moment.
 *
 * @property string|null content_type If set, this is the type of the Eloquent model that provides additional
 * content to this item.
 * @property integer|null content_id If set, this is the ID of the Eloquent model that provides additional
 * content to this item.
 *
 * @property TeachingModule module Teaching module related to this model.
 * @property Folder|null folder Folder that this model belongs to, if any.
 * @property Model|null content Additional content for this item, if any.
 */
class TeachingModuleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_module_id',
        'folder_id',
        'title',
        'description_markdown',
        'available',
        'available_from',
        'available_until',
        'content_type',
        'content_id',
    ];

    protected $casts = [
        'available' => 'boolean',
    ];

    protected $dates = [
        'available_from',
        'available_until'
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function (TeachingModuleItem $item) {
           if (is_null($item->content_type) != is_null($item->content_id)) {
               Log::error('Content type and content ID must be set or unset at the same time.');
               return false;
           }

           if (!is_null($item->content_type) && !is_null($item->content_id)) {
               if ($item->content()->doesntExist()) {
                   Log::error('Content type and content ID must reference a valid model.');
                   return false;
               }
           }

           if (!is_null($item->available_from)
               && !is_null($item->available_until)
               && !$item->available_from->isBefore($item->available_until)) {
               Log::error('"Available from" must be before "Available until"');
               return false;
           }
        });

        // Delete the content when the module item is deleted
        static::deleting(function (TeachingModuleItem $item) {
            if (isset($item->content)) {
                $item->content->delete();
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function module() {
        return $this->belongsTo('App\TeachingModule', 'teaching_module_id');
    }

    /**
     * @return BelongsTo
     */
    public function folder() {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return MorphTo
     */
    public function content() {
        return $this->morphTo();
    }

    /**
     * @return TeachingModuleItem[] with the {@link TeachingModuleItem}s needed to get here from the root of the module.
     */
    public function path() {
        $path = [$this];
        for ($folder = $this->folder; !is_null($folder); $folder = $folder->usage->folder) {
            $path[] = $folder->usage;
        }
        return array_reverse($path);
    }

    /**
     * Returns true iff this item is available to students.
     * In order to be available, the item itself and all its containers must be available.
     */
    public function isAvailable(): bool {
        foreach ($this->path() as $pathElement) {
            if (!$pathElement->isDirectlyAvailable()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true iff this item has the available flag set to true, and the date matches the specified range.
     * Does not check for the availability of the parents.
     */
    public function isDirectlyAvailable(): bool {
        if (!$this->available) {
            return false;
        }
        if ($this->available_from && !$this->available_from->lessThanOrEqualTo(Carbon::now())) {
            return false;
        }
        if ($this->available_until && !$this->available_until->greaterThanOrEqualTo(Carbon::now())) {
            return false;
        }
        return true;
    }

    /**
     * Returns the shortened and lowercased type of content for this module item (e.g. "folder", "assessment").
     */
    public function getSimpleContentType(): ?string
    {
        if ($this->content_type) {
            $parts = explode('\\', $this->content_type);
            return strtolower($parts[count($parts) - 1]);
        } else {
            return null;
        }
    }
}
