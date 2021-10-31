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

namespace Tests\Feature\Controller\Auth;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function testListEmpty() {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tokens.show'));
        $response->assertSuccessful();
        $response->assertSee('No tokens');
    }

    public function testListOne() {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $response = $this->actingAs($user)->get(route('tokens.show'));
        $response->assertSuccessful();
        $response->assertSee($token->accessToken->name);
    }

    public function testDestroyOwner() {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $response = $this->actingAs($user)
            ->delete(route('tokens.destroy', ['token' => $token->accessToken->id]));
        $response->assertRedirect();
        $this->assertDeleted($token->accessToken);
    }

    public function testDestroyNonOwner() {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $response = $this->actingAs(User::factory()->create())
            ->delete(route('tokens.destroy', ['token' => $token->accessToken->id]));
        $response->assertForbidden();
    }
}
