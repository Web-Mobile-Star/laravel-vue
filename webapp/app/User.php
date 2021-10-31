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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id Unique ID for the object.
 * @property string $name Name of the user.
 * @property string $email Email of the user.
 * @property Carbon created_at Timestamp when the model was created.
 * @property Carbon updated_at Timestamp when the model was updated.
 *
 * @property Collection tokens Personal access tokens managed by Laravel Sanctum.
 *
 * @property string guid This is for storing your LDAP users objectguid. It is needed for locating and synchronizing
 * your LDAP user to the database.
 * @property string domain This is for storing your LDAP users connection name. It is needed for storing your
 * configured LDAP connection name of the user.
 */
class User extends Authenticatable implements LdapAuthenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use AuthenticatesWithLdap;

    // Integrates the Spatie permissions management layer:
    // https://docs.spatie.be/laravel-permission/v3/basic-usage/basic-usage/
    use HasRoles;

    /**
     * @var string SUPER_ADMIN_ROLE Name of the role that is given all permissions by default.
     */
    const SUPER_ADMIN_ROLE = 'Super Admin';
    const ADMIN_MODE_ENABLED_KEY = 'admin_mode.enabled';

    /**
     * Needed to force spatie/laravel-permissions to go through the right guard, when LDAP auth is on.
     */
    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'guid', 'domain'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /** @var array Cache of enrolments for this users (depending on the module). */
    private $moduleUsers = [];

    /**
     * If the authenticated user has superuser privileges, this method enables / disables them for the session.
     * @param bool $enabled If TRUE, enables superuser privileges if available. Otherwise, disables them.
     */
    public static function setAdminMode(bool $enabled) {
        session([self::ADMIN_MODE_ENABLED_KEY => $enabled]);
    }

    /**
     * Returns TRUE iff admin mode is enabled in this session, FALSE otherwise.
     */
    public static function isAdminModeEnabled(): bool {
        return session(self::ADMIN_MODE_ENABLED_KEY, false);
    }

    /**
     * Returns TRUE iff this user can be considered to have all permissions by default.
     */
    public function isAdmin(): bool {
        return $this->hasRole(self::SUPER_ADMIN_ROLE) && self::isAdminModeEnabled();
    }

    /**
     * Returns the teaching module user for this user in the specified module, if any.
     * We keep it stored between multiple calls, to reduce the number of DB queries.
     *
     * @param TeachingModule $module
     * @return TeachingModuleUser|null
     */
    public function moduleUser(TeachingModule $module) {
        if (!array_key_exists($module->id, $this->moduleUsers)) {
            /** @var TeachingModuleUser $moduleUser */
            $moduleUser = $module->users()->where('user_id', $this->id)->first();
            $this->moduleUsers[$module->id] = $moduleUser;
            return $moduleUser;
        }
        return $this->moduleUsers[$module->id];
    }

}
