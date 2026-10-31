<?php

namespace Tests\Feature\Api\Admin;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_notification_logs(): void
    {
        $this->getJson('/api/admin/notification-logs')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/notification-logs')->assertForbidden();
    }

    public function test_administrator_can_list_notification_logs(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $target = User::factory()->create();
        NotificationLog::record('email', 'reset_password', $target->id, $target->email, 'sent');
        NotificationLog::record('whatsapp', 'reset_password', $target->id, '08123456789', 'skipped', 'FONNTE_TOKEN belum diisi.');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/notification-logs');

        $response->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user.id', $target->id);
    }

    public function test_filters_by_channel(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        NotificationLog::record('email', 'reset_password', $admin->id, 'a@example.com', 'sent');
        NotificationLog::record('whatsapp', 'reset_password', $admin->id, '08123456789', 'sent');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/notification-logs?channel=whatsapp');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.channel', 'whatsapp');
    }

    public function test_filters_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        NotificationLog::record('whatsapp', 'reset_password', $admin->id, '08123456789', 'sent');
        NotificationLog::record('whatsapp', 'reset_password', $admin->id, '08123456789', 'failed', 'Ditolak Fonnte.');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/notification-logs?status=failed');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.error_message', 'Ditolak Fonnte.');
    }

    public function test_filters_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        NotificationLog::record('email', 'reset_password', $admin->id, 'a@example.com', 'sent');
        NotificationLog::record('email', 'laporan_selesai', $admin->id, 'a@example.com', 'sent');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/notification-logs?type=laporan_selesai');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'laporan_selesai');
    }
}
