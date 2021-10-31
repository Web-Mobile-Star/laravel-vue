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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait for Eloquent models which adds an availableChildren() on top of an existing
 * children() method, using the availability flag and date range.
 *
 * @property HasMany children
 * @property HasMany availableChildren
 */
trait HasAvailableChildren
{
    abstract public function children(): HasMany;

    /**
     * Returns the items directly contained within this module which are availble to students
     * in the current server date and time.
     */
    public function availableChildren(): HasMany {
        $now = Carbon::now();
        return $this->children()
            ->where('available', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('available_from')
                      ->orWhereDate('available_from', '<', $now)
                      ->orWhere(function ($query) use ($now) {
                          $query->whereDate('available_from', '=', $now)
                                ->whereTime('available_from', '<=', $now);
                      });
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('available_until')
                      ->orWhereDate('available_until', '>', $now)
                      ->orWhere(function ($query) use ($now) {
                          $query->whereDate('available_until', '=', $now)
                                ->whereTime('available_until', '>', $now);
                      });
            });
    }

    /**
     * Returns the children visible to the current user within this model.
     */
    public function childrenVisibleByUser(): Collection {
        $sortByType = function ($e) {
            if ($e->content_type === 'App\Folder') {
                return -2;
            } else if ($e->content_type === 'App\Assessment') {
                return -1;
            } else {
                return 0;
            }
        };

        /** @var User $user */
        $user = Auth::user();
        if ($user->can('viewUnavailableItems',  $this)) {
            $items = $this->children->sortBy('title')->sortBy($sortByType);
        } else if ($user->can('viewAvailableItems', $this)) {
            $items = $this->availableChildren->sortBy('title')->sortBy($sortByType);
        } else {
            $items = new Collection();
        }

        return $items;
    }
}
