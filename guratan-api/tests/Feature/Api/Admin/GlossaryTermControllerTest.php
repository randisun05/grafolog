<?php

namespace Tests\Feature\Api\Admin;

use App\Models\GlossaryTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlossaryTermControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_terms(): void
    {
        $this->postJson('/api/admin/glossary-terms', ['istilah' => 'Baseline', 'definisi' => 'x'])->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_terms(): void
    {
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/glossary-terms', ['istilah' => 'Baseline', 'definisi' => 'x'])
            ->assertForbidden();
    }

    public function test_admin_can_create_update_delete_term(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/glossary-terms', ['istilah' => 'Baseline', 'definisi' => 'Garis dasar tulisan.']);
        $response->assertCreated()->assertJsonPath('istilah', 'Baseline');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_istilah']);

        $term = GlossaryTerm::first();
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/glossary-terms/{$term->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_istilah']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/glossary-terms/{$term->id}")
            ->assertOk();
        $this->assertDatabaseMissing('glossary_terms', ['id' => $term->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_istilah']);
    }
}
