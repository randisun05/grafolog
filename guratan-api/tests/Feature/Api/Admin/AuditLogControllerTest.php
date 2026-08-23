<?php

namespace Tests\Feature\Api\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_audit_logs(): void
    {
        $this->getJson('/api/admin/audit-logs')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    public function test_administrator_can_list_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $actor = User::factory()->create();
        AuditLog::record('ubah_harga', 'App\\Models\\PricingPlan', 1, $actor->id, '127.0.0.1');
        AuditLog::record('buat_kode_diskon', 'App\\Models\\DiscountCode', 2, $actor->id, '127.0.0.1');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs');

        $response->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.actor.id', $actor->id);
    }

    public function test_filters_by_aksi(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        AuditLog::record('ubah_harga', 'App\\Models\\PricingPlan', 1, $admin->id, null);
        AuditLog::record('buat_kode_diskon', 'App\\Models\\DiscountCode', 2, $admin->id, null);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs?aksi=harga');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.aksi', 'ubah_harga');
    }

    public function test_filters_by_actor(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $otherAdmin = User::factory()->create(['role' => 'administrator']);
        AuditLog::record('ubah_harga', 'App\\Models\\PricingPlan', 1, $admin->id, null);
        AuditLog::record('ubah_harga', 'App\\Models\\PricingPlan', 2, $otherAdmin->id, null);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/audit-logs?actor_user_id={$otherAdmin->id}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.actor_user_id', $otherAdmin->id);
    }
}
