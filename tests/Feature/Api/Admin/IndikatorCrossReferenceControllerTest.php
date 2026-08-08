<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorCrossReferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function indikator(string $kode): Indikator
    {
        $sindrom = Sindrom::firstOrCreate(['kode_romawi' => 'I'], ['nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);
        $aspek = Aspek::firstOrCreate(['kode' => '01'], ['sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);

        return Indikator::create(['kode' => $kode, 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => "Indikator $kode"]);
    }

    public function test_guest_cannot_manage_cross_references(): void
    {
        $this->getJson('/api/admin/knowledge/cross-references')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/admin/knowledge/cross-references', ['indikator_sumber_raw' => 'X', 'mereferensikan_ke_kode' => "01-2'"])
            ->assertForbidden();
    }

    public function test_administrator_can_create_matched_cross_reference(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sumber = $this->indikator("01-1'");
        $this->indikator("01-2'");

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/cross-references', [
            'indikator_sumber_raw' => 'Authoritarian - 01-1',
            'indikator_sumber_id' => $sumber->id,
            'mereferensikan_ke_kode' => "01-2'",
        ]);

        $response->assertCreated()
            ->assertJsonPath('match_status', 'matched')
            ->assertJsonPath('aktif', true);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_referensi_silang', 'actor_user_id' => $admin->id]);
    }

    public function test_target_kode_that_does_not_exist_is_unmatched(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sumber = $this->indikator("01-1'");

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/cross-references', [
            'indikator_sumber_raw' => 'Authoritarian - 01-1',
            'indikator_sumber_id' => $sumber->id,
            'mereferensikan_ke_kode' => "99-9'",
        ]);

        $response->assertCreated()->assertJsonPath('match_status', 'unmatched');
    }

    public function test_administrator_can_toggle_aktif(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $row = IndikatorCrossReference::create([
            'indikator_sumber_raw' => 'X', 'mereferensikan_ke_kode' => "01-2'", 'match_status' => 'unmatched',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/cross-references/{$row->id}", [
            'aktif' => false,
        ]);

        $response->assertOk()->assertJsonPath('aktif', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_referensi_silang', 'actor_user_id' => $admin->id]);
    }

    public function test_updating_target_recomputes_match_status(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sumber = $this->indikator("01-1'");
        $this->indikator("01-2'");
        $row = IndikatorCrossReference::create([
            'indikator_sumber_raw' => 'X', 'indikator_sumber_id' => $sumber->id,
            'mereferensikan_ke_kode' => "99-9'", 'match_status' => 'unmatched',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/cross-references/{$row->id}", [
            'mereferensikan_ke_kode' => "01-2'",
        ]);

        $response->assertOk()->assertJsonPath('match_status', 'matched');
    }

    public function test_administrator_can_delete_cross_reference(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $row = IndikatorCrossReference::create([
            'indikator_sumber_raw' => 'X', 'mereferensikan_ke_kode' => "01-2'", 'match_status' => 'unmatched',
        ]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/cross-references/{$row->id}")
            ->assertOk();

        $this->assertDatabaseMissing('indikator_cross_reference', ['id' => $row->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_referensi_silang', 'actor_user_id' => $admin->id]);
    }

    public function test_index_filters_by_aktif_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        IndikatorCrossReference::create(['indikator_sumber_raw' => 'Authoritarian line', 'mereferensikan_ke_kode' => "01-2'", 'match_status' => 'unmatched', 'aktif' => true]);
        IndikatorCrossReference::create(['indikator_sumber_raw' => 'Something else', 'mereferensikan_ke_kode' => "02-1'", 'match_status' => 'unmatched', 'aktif' => false]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/cross-references?aktif=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.indikator_sumber_raw', 'Something else');

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/cross-references?search=Authoritarian')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_indikator_options_endpoint_returns_full_unpaginated_list(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->indikator("01-1'");
        $this->indikator("01-2'");

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/indikator-options')
            ->assertOk()->assertJsonCount(2);
    }
}
