<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_older_than_expiration_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('guratan-web')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $this->travel(config('sanctum.expiration') + 1)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
