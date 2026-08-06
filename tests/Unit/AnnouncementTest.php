<?php

namespace Tests\Unit;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_untargeted_announcement_is_visible_to_any_role(): void
    {
        $announcement = Announcement::create(['title' => 'T', 'body' => 'B']);
        $client = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->assertTrue($announcement->isVisibleTo($client));
        $this->assertTrue($announcement->isVisibleTo($admin));
    }

    public function test_inactive_announcement_is_not_visible(): void
    {
        $announcement = Announcement::create(['title' => 'T', 'body' => 'B', 'is_active' => false]);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertFalse($announcement->isVisibleTo($user));
    }

    public function test_not_yet_started_announcement_is_not_visible(): void
    {
        $announcement = Announcement::create(['title' => 'T', 'body' => 'B', 'starts_at' => now()->addDay()]);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertFalse($announcement->isVisibleTo($user));
    }

    public function test_ended_announcement_is_not_visible(): void
    {
        $announcement = Announcement::create(['title' => 'T', 'body' => 'B', 'ends_at' => now()->subDay()]);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertFalse($announcement->isVisibleTo($user));
    }

    public function test_role_targeting_is_enforced(): void
    {
        $announcement = Announcement::create(['title' => 'T', 'body' => 'B', 'target_roles' => ['hr', 'administrator']]);
        $hr = User::factory()->create(['role' => 'hr']);
        $client = User::factory()->create(['role' => 'user']);

        $this->assertTrue($announcement->isVisibleTo($hr));
        $this->assertFalse($announcement->isVisibleTo($client));
    }
}
