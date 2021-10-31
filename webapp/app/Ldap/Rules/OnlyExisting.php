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

namespace App\Ldap\Rules;

use App\TeachingModuleUser;
use LdapRecord\Laravel\Auth\Rule;

/**
 * Only users that are already in the database may log in, even if
 * they have valid LDAP credentials.
 *
 * This means that we have to either manually imported it via Artisan
 * ldap:import, or via some import mechanism in the web UI (e.g. reading
 * a CSV).
 */
class OnlyExisting extends Rule
{
    /**
     * Check if the rule passes validation.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->model->exists;
    }
}
