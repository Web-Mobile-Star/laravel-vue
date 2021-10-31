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

namespace Tests\Feature;

use App\User;
use Database\Seeders\DevelopmentUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        // Needed due to https://github.com/DirectoryTree/LdapRecord-Laravel/issues/230
        DirectoryEmulator::teardown();

        parent::tearDown();
    }

    public function testHomeRequiresAuth()
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));
    }

    public function testLoginForm()
    {
        $response = $this->get(route('login'));
        $response->assertSuccessful();
        $response->assertSessionHasNoErrors();
    }

    public function testRegularUserDoesNotSeeSubmit()
    {
        $this->seed();
        $this->seed(DevelopmentUserSeeder::class);
        $user = User::where('email', DevelopmentUserSeeder::NORMAL_EMAIL)->first();

        $response = $this->post(route('login'), [
            'email' => DevelopmentUserSeeder::NORMAL_EMAIL,
            'password' => DevelopmentUserSeeder::NORMAL_PASSWORD
        ]);
        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $response = $this->get(route('home'));
        $response->assertDontSee('Jobs');

        // User shouldn't be able to switch to admin mode
        $this->assertEquals(false, User::isAdminModeEnabled());
        $response = $this->actingAs($user)->post(route('enableAdmin'));
        $response->assertForbidden();
        $this->assertEquals(false, User::isAdminModeEnabled());
    }

    public function testSuperAdminSeesSubmit()
    {
        $this->seed();
        $this->seed(DevelopmentUserSeeder::class);

        $response = $this->post(route('login'), [
            'email' => DevelopmentUserSeeder::SUPER_ADMIN_EMAIL,
            'password' => DevelopmentUserSeeder::SUPER_ADMIN_PASSWORD
        ]);
        $response->assertRedirect(route('home'));

        $user = User::where('email', DevelopmentUserSeeder::SUPER_ADMIN_EMAIL)->first();
        $this->assertAuthenticatedAs($user);

        // By default, admin mode is NOT on.
        $response = $this->get(route('home'));
        $response->assertDontSee('Jobs');

        // Switch it on!
        $response = $this->post(route('enableAdmin'));
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Now we should be able to see it...
        $response = $this->get(route('home'));
        $response->assertSee('Jobs');

        // Switch it off!
        $response = $this->post(route('disableAdmin'));
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Now we should not be able to see it anymore
        $response = $this->get(route('home'));
        $response->assertDontSee('Jobs');
    }

    public function testLDAPExistingUserCanLogin()
    {
        $this->seed();
        $this->seed(DevelopmentUserSeeder::class);
        $user = User::where('email', DevelopmentUserSeeder::LDAP_EXISTING_EMAIL)->first();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => DevelopmentUserSeeder::LDAP_EXISTING_PASSWORD
        ]);
        $response->assertRedirect(route('home'));
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function testLDAPMissingUserCannotLogin()
    {
        $this->seed();
        $this->seed(DevelopmentUserSeeder::class);

        $response = $this->post(route('login'), [
            'email' => DevelopmentUserSeeder::LDAP_MISSING_EMAIL,
            'password' => DevelopmentUserSeeder::LDAP_MISSING_PASSWORD
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertNull(Auth::user());
    }

}
