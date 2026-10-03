<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_link_pointing_to_frontend(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.guratan.test']);
        $user = User::factory()->create(['email' => 'lupa@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'lupa@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_starts_with($mail->actionUrl, 'https://app.guratan.test/reset-password?token=');
        });
    }

    public function test_forgot_password_response_is_identical_for_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'tidakada@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email tersebut terdaftar, tautan atur ulang kata sandi sudah kami kirim.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_changes_password_and_revokes_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'LamaSekali123']);
        $user->createToken('lama');

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'BaruSekali123',
            'password_confirmation' => 'BaruSekali123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => 'reset@example.com', 'password' => 'BaruSekali123'])->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'palsu',
            'email' => 'reset2@example.com',
            'password' => 'BaruSekali123',
            'password_confirmation' => 'BaruSekali123',
        ])->assertUnprocessable();
    }

    public function test_forgot_password_not_blocked_by_earlier_login_attempts(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'sering@example.com']);

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/auth/login', ['email' => 'sering@example.com', 'password' => 'salah'.$i]);
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'sering@example.com'])->assertOk();
    }
}
