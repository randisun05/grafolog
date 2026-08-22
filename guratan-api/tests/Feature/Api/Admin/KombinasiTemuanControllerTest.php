<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\KombinasiTemuan;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KombinasiTemuanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function aspek(): Aspek
    {
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);

        return Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);
    }

    public function test_guest_cannot_manage_kombinasi(): void
    {
        $this->getJson('/api/admin/knowledge/kombinasi')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/knowledge/kombinasi')->assertForbidden();
    }

    public function test_administrator_can_create_and_list_temuan(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/kombinasi', [
            'nama' => 'Pola Ambisi Tersembunyi',
            'teks_interpretasi' => 'Kombinasi ini menandakan ambisi yang tidak diekspresikan terbuka.',
            'logika_gabung' => 'AND',
        ]);
        $response->assertCreated()->assertJsonPath('nama', 'Pola Ambisi Tersembunyi');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_kombinasi_temuan', 'actor_user_id' => $admin->id]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/kombinasi')
            ->assertOk()->assertJsonCount(1);
    }

    public function test_store_rejects_missing_fields(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/kombinasi', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama', 'teks_interpretasi']);
    }

    public function test_administrator_can_update_and_delete_temuan(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $temuan = KombinasiTemuan::create(['nama' => 'Lama', 'teks_interpretasi' => 'x', 'logika_gabung' => 'OR']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/kombinasi/{$temuan->id}", [
            'nama' => 'Baru', 'teks_interpretasi' => 'y', 'logika_gabung' => 'AND',
        ])->assertOk()->assertJsonPath('nama', 'Baru');

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/kombinasi/{$temuan->id}")->assertOk();
        $this->assertDatabaseMissing('kombinasi_temuan', ['id' => $temuan->id]);
    }

    public function test_administrator_can_add_syarat_of_each_level(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $sindrom = $aspek->sindrom;
        $indikator = Indikator::create(['kode' => '01-1a', 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Indikator X']);
        $temuan = KombinasiTemuan::create(['nama' => 'Pola', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/kombinasi/{$temuan->id}/syarat", [
            'level' => 'indikator', 'indikator_id' => $indikator->id, 'kondisi' => 'tercentang',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/kombinasi/{$temuan->id}/syarat", [
            'level' => 'aspek', 'aspek_id' => $aspek->id, 'kondisi' => 'high',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/kombinasi/{$temuan->id}/syarat", [
            'level' => 'sindrom', 'sindrom_id' => $sindrom->id, 'kondisi' => 'low',
        ])->assertCreated();

        $this->assertDatabaseCount('kombinasi_syarat', 3);
    }

    public function test_syarat_rejects_mismatched_level_and_target(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $temuan = KombinasiTemuan::create(['nama' => 'Pola', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/kombinasi/{$temuan->id}/syarat", [
            'level' => 'indikator', 'aspek_id' => $aspek->id, 'kondisi' => 'tercentang',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['indikator_id']);
    }

    public function test_syarat_rejects_invalid_kondisi_for_level(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $temuan = KombinasiTemuan::create(['nama' => 'Pola', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/kombinasi/{$temuan->id}/syarat", [
            'level' => 'aspek', 'aspek_id' => $aspek->id, 'kondisi' => 'tercentang',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['kondisi']);
    }

    public function test_administrator_can_delete_syarat(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $temuan = KombinasiTemuan::create(['nama' => 'Pola', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);
        $syarat = $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => $aspek->id, 'kondisi' => 'high']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/kombinasi-syarat/{$syarat->id}")->assertOk();

        $this->assertDatabaseMissing('kombinasi_syarat', ['id' => $syarat->id]);
    }

    public function test_deleting_temuan_cascades_its_syarat(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $temuan = KombinasiTemuan::create(['nama' => 'Pola', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => $aspek->id, 'kondisi' => 'high']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/kombinasi/{$temuan->id}")->assertOk();

        $this->assertDatabaseCount('kombinasi_syarat', 0);
    }
}
