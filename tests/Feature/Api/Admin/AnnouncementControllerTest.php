<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_announcements(): void
    {
        $this->getJson('/api/admin/announcements')->assertUnauthorized();
        $this->postJson('/api/admin/announcements', [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/admin/announcements', ['title' => 'X', 'body' => 'Y'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/announcements', [
            'title' => 'Promo Agustus',
            'body' => 'Diskon 10% pakai kode HEMAT10.',
            'target_roles' => ['user'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Promo Agustus')
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_pengumuman', 'actor_user_id' => $admin->id]);
    }

    public function test_title_and_body_are_required(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/announcements', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_administrator_can_edit_announcement_fully(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $announcement = Announcement::create(['title' => 'Lama', 'body' => 'Isi lama.']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/announcements/{$announcement->id}", [
            'title' => 'Baru',
            'body' => 'Isi baru.',
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('title', 'Baru')->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_pengumuman', 'actor_user_id' => $admin->id]);
    }

    public function test_index_lists_all_announcements_including_inactive(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Announcement::create(['title' => 'A', 'body' => 'X', 'is_active' => true]);
        Announcement::create(['title' => 'B', 'body' => 'Y', 'is_active' => false]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/announcements')
            ->assertOk()->assertJsonCount(2, 'data');
    }
}
