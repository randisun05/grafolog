<?php

namespace Tests\Feature\Api\Admin;

use App\Models\ScoringRuleBand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringRuleBandControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_bands(): void
    {
        $this->getJson('/api/admin/knowledge/scoring-rule-bands')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/admin/knowledge/scoring-rule-bands', ['polaritas' => 'HIJAU', 'label' => 'X', 'rentang_skor' => '1-3'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_band(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/scoring-rule-bands', [
            'polaritas' => 'HIJAU',
            'label' => 'Nilai Rendah',
            'rentang_skor' => '1-3',
        ]);

        $response->assertCreated()->assertJsonPath('label', 'Nilai Rendah');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_band_skor', 'actor_user_id' => $admin->id]);
    }

    public function test_invalid_polaritas_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/scoring-rule-bands', [
            'polaritas' => 'KUNING',
            'label' => 'X',
            'rentang_skor' => '1-3',
        ])->assertUnprocessable()->assertJsonValidationErrors(['polaritas']);
    }

    public function test_administrator_can_update_band(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $band = ScoringRuleBand::create(['polaritas' => 'HIJAU', 'label' => 'Lama', 'rentang_skor' => '1-3']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/scoring-rule-bands/{$band->id}", [
            'label' => 'Baru',
        ])->assertOk()->assertJsonPath('label', 'Baru');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_band_skor', 'actor_user_id' => $admin->id]);
    }

    public function test_administrator_can_delete_band(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $band = ScoringRuleBand::create(['polaritas' => 'HIJAU', 'label' => 'X', 'rentang_skor' => '1-3']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/scoring-rule-bands/{$band->id}")
            ->assertOk();

        $this->assertDatabaseMissing('scoring_rule_band', ['id' => $band->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_band_skor', 'actor_user_id' => $admin->id]);
    }

    public function test_index_lists_all_bands(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        ScoringRuleBand::create(['polaritas' => 'HIJAU', 'label' => 'A', 'rentang_skor' => '1-3']);
        ScoringRuleBand::create(['polaritas' => 'MERAH', 'label' => 'B', 'rentang_skor' => '4-6']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/scoring-rule-bands')
            ->assertOk()->assertJsonCount(2);
    }
}
