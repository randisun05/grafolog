<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'budi@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'role' => 'user']);
    }

    public function test_register_can_create_grafolog_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Grafolog Satu',
            'email' => 'grafolog@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'grafolog',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'grafolog');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'weak@example.com',
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_register_rejects_arbitrary_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_register_rejects_administrator_and_supervisor_roles(): void
    {
        // MGA Fase 05: hanya user/grafolog boleh daftar sendiri. administrator/
        // supervisor cuma bisa dibuat lewat POST /api/admin/users oleh admin.
        foreach (['administrator', 'supervisor'] as $role) {
            $response = $this->postJson('/api/auth/register', [
                'name' => 'Someone',
                'email' => "sneaky-$role@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => $role,
            ]);

            $response->assertUnprocessable()->assertJsonValidationErrors('role');
        }
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login2@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login2@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }
}
