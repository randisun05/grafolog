<?php

namespace Tests\Feature\Api\Admin;

use App\Models\ContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentBlockControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_content(): void
    {
        $this->getJson('/api/admin/content')->assertUnauthorized();
        $this->putJson('/api/admin/content/landing_eyebrow', ['value' => 'X'])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')
            ->putJson('/api/admin/content/landing_eyebrow', ['value' => 'X'])
            ->assertForbidden();
    }

    public function test_administrator_can_update_editable_key(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/content/landing_tagline', ['value' => 'Tagline baru yang lebih bagus.']);

        $response->assertOk()->assertJsonPath('value', 'Tagline baru yang lebih bagus.');
        $this->assertDatabaseHas('content_blocks', [
            'key' => 'landing_tagline', 'value' => 'Tagline baru yang lebih bagus.', 'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_konten', 'actor_user_id' => $admin->id]);
    }

    public function test_unknown_key_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/content/not_a_real_key', ['value' => 'X'])
            ->assertNotFound();
    }

    public function test_updating_existing_block_overwrites_not_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        ContentBlock::create(['key' => 'landing_cta_label', 'value' => 'Lama']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/content/landing_cta_label', ['value' => 'Baru'])
            ->assertOk();

        $this->assertDatabaseCount('content_blocks', 1);
        $this->assertDatabaseHas('content_blocks', ['key' => 'landing_cta_label', 'value' => 'Baru']);
    }

    public function test_administrator_can_update_new_landing_section_key(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/content/landing_compare_heading', ['value' => 'Judul baru'])
            ->assertOk()
            ->assertJsonPath('value', 'Judul baru');
    }

    public function test_administrator_can_update_list_key_with_json_value(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $json = json_encode(['Item satu', 'Item dua', 'Item tiga']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/content/landing_compare_old', ['value' => $json]);

        $response->assertOk();
        $this->assertDatabaseHas('content_blocks', ['key' => 'landing_compare_old', 'value' => $json]);
    }

    public function test_index_lists_all_blocks(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        ContentBlock::create(['key' => 'landing_eyebrow', 'value' => 'A']);
        ContentBlock::create(['key' => 'landing_tagline', 'value' => 'B']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/content')
            ->assertOk()->assertJsonCount(2);
    }
}
