<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
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

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_role_targeted_announcement_hidden_from_other_roles(): void
    {
        Announcement::create(['title' => 'Untuk HR', 'body' => 'Fitur baru.', 'target_roles' => ['hr']]);
        $client = User::factory()->create(['role' => 'user']);
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($client, 'sanctum')->getJson('/api/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($hr, 'sanctum')->getJson('/api/announcements')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_inactive_announcement_excluded(): void
    {
        Announcement::create(['title' => 'Nonaktif', 'body' => 'X', 'is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/announcements')->assertOk()->assertJsonCount(0, 'data');
    }

    // --- read state ---

    public function test_new_announcement_is_unread_by_default(): void
    {
        Announcement::create(['title' => 'Baru', 'body' => 'X']);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/announcements');

        $response->assertOk()
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_marking_one_announcement_read_updates_unread_count(): void
    {
        $announcement = Announcement::create(['title' => 'A', 'body' => 'X']);
        Announcement::create(['title' => 'B', 'body' => 'Y']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/announcements/{$announcement->id}/read")->assertOk();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/announcements');
        $response->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertDatabaseHas('announcement_reads', ['announcement_id' => $announcement->id, 'user_id' => $user->id]);
    }

    public function test_marking_read_twice_does_not_duplicate_or_error(): void
    {
        $announcement = Announcement::create(['title' => 'A', 'body' => 'X']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/announcements/{$announcement->id}/read")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/announcements/{$announcement->id}/read")->assertOk();

        $this->assertSame(1, AnnouncementRead::where('announcement_id', $announcement->id)->where('user_id', $user->id)->count());
    }

    public function test_cannot_mark_read_an_announcement_not_visible_to_you(): void
    {
        $announcement = Announcement::create(['title' => 'Untuk HR', 'body' => 'X', 'target_roles' => ['hr']]);
        $client = User::factory()->create(['role' => 'user']);

        $this->actingAs($client, 'sanctum')->postJson("/api/announcements/{$announcement->id}/read")->assertNotFound();
    }

    public function test_read_state_is_per_user(): void
    {
        $announcement = Announcement::create(['title' => 'A', 'body' => 'X']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA, 'sanctum')->postJson("/api/announcements/{$announcement->id}/read")->assertOk();

        $this->actingAs($userA, 'sanctum')->getJson('/api/announcements')->assertJsonPath('unread_count', 0);
        $this->actingAs($userB, 'sanctum')->getJson('/api/announcements')->assertJsonPath('unread_count', 1);
    }

    public function test_mark_all_read(): void
    {
        Announcement::create(['title' => 'A', 'body' => 'X']);
        Announcement::create(['title' => 'B', 'body' => 'Y']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/announcements/read-all')->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/announcements')->assertJsonPath('unread_count', 0);
    }
}
