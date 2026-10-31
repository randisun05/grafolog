<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Workshop Grafologi Dasar',
            'description' => '<p>Belajar dasar grafologi bersama praktisi.</p>',
            'starts_at' => now()->addDays(7)->toDateTimeString(),
            'status' => 'draft',
        ], $overrides);
    }

    public function test_guest_cannot_manage_events(): void
    {
        $this->postJson('/api/admin/events', $this->validPayload())->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_events(): void
    {
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/events', $this->validPayload())
            ->assertForbidden();
    }

    public function test_admin_can_create_event_with_cover_and_slug_is_generated(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/events', $this->validPayload([
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            'capacity' => 2,
        ]));

        $response->assertCreated()
            ->assertJsonPath('slug', 'workshop-grafologi-dasar')
            ->assertJsonPath('spots_remaining', 2);

        Storage::disk('public')->assertExists(Event::first()->cover_image_path);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_kegiatan']);
    }

    public function test_admin_can_update_event(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $event = Event::create([
            'title' => 'Draft', 'slug' => 'draft-event', 'description' => '<p>x</p>',
            'starts_at' => now()->addDays(3), 'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/events/{$event->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_kegiatan']);
    }

    public function test_admin_index_includes_draft_events(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Event::create([
            'title' => 'Draft', 'slug' => 'draft-event', 'description' => '<p>x</p>',
            'starts_at' => now()->addDays(3), 'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/events')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
