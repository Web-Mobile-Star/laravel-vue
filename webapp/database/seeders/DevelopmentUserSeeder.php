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

namespace Database\Seeders;

use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    const NORMAL_EMAIL = 'user@example.com';
    const NORMAL_PASSWORD = 'iamnormal';

    const SUPER_ADMIN_EMAIL = 'root@example.com';
    const SUPER_ADMIN_PASSWORD = 'iamroot';

    const LDAP_EXISTING_EMAIL = 'ldapuser@example.com';
    const LDAP_EXISTING_PASSWORD = 'iamldap';

    const LDAP_MISSING_EMAIL = 'ldapuser2@example.com';
    const LDAP_MISSING_PASSWORD = 'iamldap';

    /**
     * Creates a few example users with different permissions.
     *
     * @return void
     */
    public function run()
    {
        // Superuser with all permissions
        /** @var User $adminUser */
        $adminUser = User::firstOrCreate([
            'email' => self::SUPER_ADMIN_EMAIL,
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make(self::SUPER_ADMIN_PASSWORD),
        ]);
        $adminUser->assignRole(User::SUPER_ADMIN_ROLE);

        // Regular user with no special permissions
        User::firstOrCreate([
            'email' => self::NORMAL_EMAIL,
        ], [
            'name' => 'Normal User',
            'password' => Hash::make(self::NORMAL_PASSWORD),
        ]);

        // LDAP user with no special permissions (for testing)
        Artisan::call('ldap:import', [
            'provider' => 'ldap',
            'user' => 'ldapuser',
            '--no-interaction'
        ]);
    }

}
