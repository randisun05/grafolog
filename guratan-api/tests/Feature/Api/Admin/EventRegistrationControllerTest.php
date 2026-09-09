<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function publishedEvent(): Event
    {
        return Event::create([
            'title' => 'Workshop Grafologi', 'slug' => 'workshop-grafologi', 'description' => '<p>x</p>',
            'starts_at' => now()->addDays(3), 'status' => 'published',
        ]);
    }

    public function test_guest_cannot_view_registrations(): void
    {
        $event = $this->publishedEvent();

        $this->getJson("/api/admin/events/{$event->id}/registrations")->assertUnauthorized();
    }

    public function test_non_admin_cannot_view_registrations(): void
    {
        $event = $this->publishedEvent();
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/events/{$event->id}/registrations")
            ->assertForbidden();
    }

    public function test_admin_can_list_registered_participants_only(): void
    {
        $event = $this->publishedEvent();
        $admin = User::factory()->create(['role' => 'administrator']);
        $registered = User::factory()->create(['name' => 'Budi Peserta']);
        $cancelled = User::factory()->create(['name' => 'Siti Batal']);

        $this->actingAs($registered, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();
        $this->actingAs($cancelled, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();
        $this->actingAs($cancelled, 'sanctum')->deleteJson("/api/events/{$event->id}/register")->assertOk();

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/events/{$event->id}/registrations");

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.user.name', 'Budi Peserta');
    }

    public function test_admin_can_export_participants_csv(): void
    {
        $event = $this->publishedEvent();
        $admin = User::factory()->create(['role' => 'administrator']);
        $participant = User::factory()->create(['name' => 'Budi Peserta', 'phone' => '081234567890']);
        $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/register")->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')->get("/api/admin/events/{$event->id}/registrations/export");

        $response->assertOk();
        $this->assertStringContainsString('Budi Peserta', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ekspor_peserta_kegiatan']);
    }
}
