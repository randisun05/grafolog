<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_only_published_events(): void
    {
        Event::create(['title' => 'Terbit', 'slug' => 'terbit', 'description' => '<p>x</p>', 'starts_at' => now()->addDay(), 'status' => 'published']);
        Event::create(['title' => 'Draft', 'slug' => 'draft', 'description' => '<p>x</p>', 'starts_at' => now()->addDay(), 'status' => 'draft']);

        $response = $this->getJson('/api/events');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'terbit');
    }

    public function test_upcoming_filter_excludes_past_events(): void
    {
        Event::create(['title' => 'Lalu', 'slug' => 'lalu', 'description' => '<p>x</p>', 'starts_at' => now()->subDay(), 'status' => 'published']);
        Event::create(['title' => 'Nanti', 'slug' => 'nanti', 'description' => '<p>x</p>', 'starts_at' => now()->addDay(), 'status' => 'published']);

        $response = $this->getJson('/api/events?when=upcoming');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'nanti');
    }

    public function test_past_filter_excludes_upcoming_events(): void
    {
        Event::create(['title' => 'Lalu', 'slug' => 'lalu', 'description' => '<p>x</p>', 'starts_at' => now()->subDay(), 'status' => 'published']);
        Event::create(['title' => 'Nanti', 'slug' => 'nanti', 'description' => '<p>x</p>', 'starts_at' => now()->addDay(), 'status' => 'published']);

        $response = $this->getJson('/api/events?when=past');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'lalu');
    }

    public function test_draft_event_returns_404_for_anyone(): void
    {
        Event::create(['title' => 'Draft', 'slug' => 'draft', 'description' => '<p>x</p>', 'starts_at' => now()->addDay(), 'status' => 'draft']);

        $this->getJson('/api/events/draft')->assertNotFound();
    }
}
