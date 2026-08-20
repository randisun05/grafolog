<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_announcements(): void
    {
        $this->getJson('/api/announcements')->assertUnauthorized();
    }

    public function test_untargeted_announcement_visible_to_any_authenticated_user(): void
    {
        Announcement::create(['title' => 'Selamat Datang', 'body' => 'Promo bulan ini.']);
        $client = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/announcements');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_role_targeted_announcement_hidden_from_other_roles(): void
    {
        Announcement::create(['title' => 'Untuk HR', 'body' => 'Fitur baru.', 'target_roles' => ['hr']]);
        $client = User::factory()->create(['role' => 'user']);
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($client, 'sanctum')->getJson('/api/announcements')->assertOk()->assertJsonCount(0);
        $this->actingAs($hr, 'sanctum')->getJson('/api/announcements')->assertOk()->assertJsonCount(1);
    }

    public function test_inactive_announcement_excluded(): void
    {
        Announcement::create(['title' => 'Nonaktif', 'body' => 'X', 'is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/announcements')->assertOk()->assertJsonCount(0);
    }
}
