<?php

namespace Tests\Feature\Api;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

    public function test_deactivated_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'nonaktif@example.com',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonaktif@example.com',
            'password' => 'password123',
        ]);

        $response->assertForbidden()->assertJsonPath('message', 'Akun Anda telah dinonaktifkan. Hubungi administrator.');
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

    public function test_forgot_password_sends_reset_link_for_existing_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'ada@example.com']);

        $response->assertOk();
        Mail::assertSent(ResetPasswordMail::class, fn ($mail) => str_contains($mail->resetUrl, 'ada%40example.com'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * Pesan HARUS sama persis dengan email yang benar-benar terdaftar (lihat
     * test di atas) - kalau beda, itu jadi cara mengecek email mana yang
     * punya akun (user enumeration).
     */
    public function test_forgot_password_returns_same_message_for_unknown_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'tidakada@example.com']);

        $response->assertOk()->assertJsonPath('message', 'Kalau email terdaftar, tautan reset kata sandi sudah dikirim.');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    private function extractTokenFromResetUrl(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    public function test_reset_password_with_valid_token_updates_password_and_revokes_old_tokens(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'passwordlama1']);
        $oldToken = $user->createToken('lama')->plainTextToken;

        $this->postJson('/api/auth/forgot-password', ['email' => 'ada@example.com'])->assertOk();
        $resetUrl = null;
        Mail::assertSent(ResetPasswordMail::class, function ($mail) use (&$resetUrl) {
            $resetUrl = $mail->resetUrl;

            return true;
        });
        $token = $this->extractTokenFromResetUrl($resetUrl);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'ada@example.com', 'token' => $token,
            'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('passwordbaru1', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
        // Token API lama harus dicabut - reset password bukan cuma ganti
        // password, tapi juga memutus akses yang mungkin sudah bocor.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'passwordbaru1'])
            ->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => Hash::make('token-asli'), 'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'ada@example.com', 'token' => 'token-salah',
            'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_rejects_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => Hash::make('token-asli'), 'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'ada@example.com', 'token' => 'token-asli',
            'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_rejects_when_no_request_was_ever_made(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'ada@example.com', 'token' => 'apapun',
            'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ]);

        $response->assertStatus(422);
    }
}
