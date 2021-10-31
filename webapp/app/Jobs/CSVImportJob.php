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

namespace App\Jobs;

use App\Policies\TeachingModuleUserPolicy;
use App\TeachingModule;
use App\TeachingModuleUser;
use App\User;
use App\ZipSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\LdapUserRepository;

class CSVImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var TeachingModule
     */
    private $module;

    /**
     * @var string[]
     */
    private $usernames;

    /**
     * Create a new job instance.
     *
     * @param TeachingModule $module
     * @param string[] $usernames
     */
    public function __construct(TeachingModule $module, array $usernames)
    {
        $this->onQueue(ZipSubmission::QUEUE_NON_JAVA);

        $this->module = $module;
        $this->usernames = $usernames;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Importing " . count($this->usernames) . " users into module " . $this->module->name);
        $repo = new LdapUserRepository(config('auth.providers.ldap.model'));

        foreach ($this->usernames as $username) {
            // Import LDAP user (Artisan command does not give back the user object)
            $ldapUser = $repo->query()->findByAnr($username);
            if (is_null($ldapUser)) {
                Log::warning("Could not find user $username in the LDAP server.");
            } else {
                Log::info("Importing $username into module #" . $this->module->id . " ('" . $this->module->name . "')");

                $guid = $ldapUser->getConvertedGuid();
                $email = $this->getLDAPAttribute($ldapUser, 'mail');
                $name = $this->getLDAPAttribute($ldapUser, 'cn');

                Log::debug("GUID of $username is $guid");

                // Create the user (if it does not already exist)
                /** @var User $user */
                $user = User::firstOrCreate(
                    [
                        'guid' => $guid
                    ],
                    [
                        'email' => $email,
                        'name' => $name,
                        'password' => '!', // Should not be able to log in via normal password
                        'domain' => config('ldap.default'),
                    ]
                );

                // Enrol the user as a student
                /** @var TeachingModuleUser $tmu */
                $tmu = TeachingModuleUser::firstOrCreate([
                    'teaching_module_id' => $this->module->id,
                    'user_id' => $user->id
                ]);
                $tmu->assignRole(TeachingModuleUserPolicy::STUDENT_ROLE);
            }
        }

    }

    /**
     * @param \LdapRecord\Models\Model $ldapUser
     * @param string $key
     * @return mixed
     */
    private function getLDAPAttribute(\LdapRecord\Models\Model $ldapUser, string $key)
    {
        $email = $ldapUser->getAttributeValue($key);
        if (is_array($email) && count($email) > 0) {
            $email = $email[0];
        }
        return $email;
    }
}
