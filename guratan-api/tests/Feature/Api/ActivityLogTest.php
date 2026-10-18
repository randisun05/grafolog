<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Log aktivitas ringan (2026-10-18). */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_failed_login_are_logged_without_payload(): void
    {
        $user = User::factory()->create(['email' => 'log@example.com', 'password' => 'RahasiaBanget123']);

        $this->postJson('/api/auth/login', ['email' => 'log@example.com', 'password' => 'salah'])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'log@example.com', 'password' => 'RahasiaBanget123'])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['event' => 'login_gagal', 'user_id' => null, 'status' => 422]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $user->id, 'status' => 200]);
        // Tidak ada isi request di kolom mana pun.
        $this->assertSame(0, ActivityLog::where('path', 'like', '%RahasiaBanget%')->orWhere('user_agent', 'like', '%RahasiaBanget%')->count());
    }

    public function test_write_requests_are_logged_reads_and_noise_are_not(): void
    {
        $user = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $month = now()->format('Y-m');

        $this->actingAs($user, 'sanctum')->getJson('/api/grafolog/availability')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/inbox/unread-count')->assertOk();
        $this->assertSame(0, ActivityLog::count());

        $this->actingAs($user, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $month, 'status' => 'tersedia'])->assertOk();
        $row = ActivityLog::firstOrFail();
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('request', $row->event);
        $this->assertSame('PUT', $row->method);
        $this->assertSame('/api/grafolog/availability', $row->path);
        $this->assertSame('api/grafolog/availability', $row->route);
        $this->assertSame(200, $row->status);
    }

    public function test_reads_can_be_enabled_and_logging_can_be_switched_off(): void
    {
        $user = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);

        config(['activity.log_reads' => true]);
        $this->actingAs($user, 'sanctum')->getJson('/api/grafolog/availability')->assertOk();
        $this->assertSame(1, ActivityLog::count());

        config(['activity.enabled' => false]);
        $this->actingAs($user, 'sanctum')->getJson('/api/grafolog/availability')->assertOk();
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_anonymous_requests_are_not_logged_and_admin_can_search(): void
    {
        $this->postJson('/api/self-check', ['answers' => []])->assertUnprocessable();
        $this->assertSame(0, ActivityLog::count());

        $admin = User::factory()->create(['role' => 'administrator']);
        $user = User::factory()->create(['name' => 'Budi Pencari', 'email' => 'budi@example.com']);
        ActivityLog::create(['user_id' => $user->id, 'event' => 'request', 'method' => 'POST', 'path' => '/api/orders', 'status' => 201]);
        ActivityLog::create(['user_id' => $admin->id, 'event' => 'login', 'method' => 'POST', 'path' => '/api/auth/login', 'status' => 200]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/activity-logs?q=budi')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.email', 'budi@example.com');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/activity-logs?event=login')->assertJsonCount(1, 'data');
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/activity-logs')->assertForbidden();
    }

    public function test_prune_command_deletes_only_expired_rows(): void
    {
        ActivityLog::create(['event' => 'request', 'path' => '/lama'])->forceFill(['created_at' => now()->subDays(120)])->save();
        ActivityLog::create(['event' => 'request', 'path' => '/baru'])->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->artisan('activity:prune')->assertExitCode(0);

        $this->assertSame(['/baru'], ActivityLog::pluck('path')->all());
    }
}
