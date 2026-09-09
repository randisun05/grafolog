<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function publishedEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Workshop Grafologi', 'slug' => 'workshop-grafologi', 'description' => '<p>x</p>',
            'starts_at' => now()->addDays(3), 'status' => 'published',
        ], $overrides));
    }

    public function test_guest_cannot_register(): void
    {
        $event = $this->publishedEvent();

        $this->postJson("/api/events/{$event->id}/register")->assertUnauthorized();
    }

    public function test_logged_in_user_can_register(): void
    {
        $event = $this->publishedEvent();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/register")
            ->assertCreated()
            ->assertJsonPath('status', 'registered');

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'registered']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'daftar_kegiatan']);
    }

    public function test_registration_rejected_when_event_full(): void
    {
        $event = $this->publishedEvent(['capacity' => 1]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();
        $this->actingAs($second, 'sanctum')
            ->postJson("/api/events/{$event->id}/register")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kegiatan sudah penuh.');
    }

    public function test_cannot_register_to_draft_event(): void
    {
        $event = $this->publishedEvent(['status' => 'draft']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/register")
            ->assertStatus(422);
    }

    public function test_user_can_cancel_and_re_register(): void
    {
        $event = $this->publishedEvent(['capacity' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/events/{$event->id}/register")->assertOk();
        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'cancelled']);

        // Kapasitas terbuka lagi setelah batal - dan orang lain bisa daftar.
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();

        // User asli bisa daftar ulang - updateOrCreate menimpa baris cancelled, bukan gagal unique constraint.
        $event->update(['capacity' => 5]);
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/register")
            ->assertCreated()
            ->assertJsonPath('status', 'registered');

        $this->assertDatabaseCount('event_registrations', 2);
    }

    public function test_mine_only_returns_own_registrations(): void
    {
        $event = $this->publishedEvent();
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();
        $this->actingAs($stranger, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/event-registrations/mine');

        $response->assertOk()->assertJsonCount(1);
    }
}
