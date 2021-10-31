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

namespace Tests\Feature\Controller\API;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function testCreateNoAuth() {
        $response = $this->post(route('api.tokens.create'), ['tokenName' => 'foo']);
        $response->assertUnauthorized();
    }

    public function testValidateNoAuth() {
        $response = $this->post(route('api.tokens.validate'));
        $response->assertRedirect(route('login'));
    }

    public function testCreateValidate() {
        /** @var User $user */
        $user = User::factory()->create();

        // Do a POST call with Basic HTTP authentication
        $response = $this->call('POST',
            route('api.tokens.create'),
            ['tokenName' => 'my-token'], [], [], [
                'HTTP_Authorization' => "Basic " . base64_encode($user->email . ":password"),
                'PHP_AUTH_USER' => $user->email,
                'PHP_AUTH_PW' => 'password'
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'token' => [],
            'expiration' => [],
        ]);

        // Use the token in a request
        $response = $this->call('POST',
            route('api.tokens.validate'),
            [], [], [], [
                'HTTP_Authorization' => 'Bearer ' . $response->json('token'),
            ]);
        $response->assertSuccessful();
        $response->assertExactJson(['valid' => true]);
    }

}
