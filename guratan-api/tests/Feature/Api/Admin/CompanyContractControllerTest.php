<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B2B Fase 3 - kontrak custom sales-led per perusahaan, record-only (lihat
 * migrasi/model docblock kenapa tidak ada kalkulasi tagihan otomatis).
 */
class CompanyContractControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_contracts(): void
    {
        $company = Company::create(['name' => 'PT Contoh']);

        $this->postJson("/api/admin/companies/{$company->id}/contracts", [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $company = Company::create(['name' => 'PT Contoh']);

        $this->actingAs($hr, 'sanctum')->postJson("/api/admin/companies/{$company->id}/contracts", [
            'judul' => 'Kontrak 2026', 'mulai_at' => '2026-01-01', 'status' => 'draft',
        ])->assertForbidden();
    }

    public function test_administrator_can_create_contract(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Rekrut']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/companies/{$company->id}/contracts", [
            'judul' => 'Kontrak Tahunan 2026',
            'catatan' => 'Paket 50 kandidat, ditagih manual per kuartal.',
            'nilai_kontrak' => 25000000,
            'mulai_at' => '2026-01-01',
            'berakhir_at' => '2026-12-31',
            'status' => 'aktif',
        ]);

        $response->assertCreated()->assertJsonPath('judul', 'Kontrak Tahunan 2026')->assertJsonPath('status', 'aktif');
        $this->assertDatabaseHas('company_contracts', ['company_id' => $company->id, 'judul' => 'Kontrak Tahunan 2026']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_kontrak_b2b']);
    }

    public function test_berakhir_at_must_not_be_before_mulai_at(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Rekrut']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/companies/{$company->id}/contracts", [
            'judul' => 'Kontrak Salah', 'mulai_at' => '2026-06-01', 'berakhir_at' => '2026-01-01', 'status' => 'draft',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('berakhir_at');
    }

    public function test_administrator_can_update_contract(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Rekrut']);
        $contract = $company->contracts()->create([
            'judul' => 'Draft Awal', 'mulai_at' => '2026-01-01', 'status' => 'draft',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/company-contracts/{$contract->id}", [
            'judul' => 'Draft Awal', 'mulai_at' => '2026-01-01', 'status' => 'aktif',
        ]);

        $response->assertOk()->assertJsonPath('status', 'aktif');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_kontrak_b2b', 'target_id' => $contract->id]);
    }

    public function test_administrator_can_delete_contract(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Rekrut']);
        $contract = $company->contracts()->create([
            'judul' => 'Akan Dihapus', 'mulai_at' => '2026-01-01', 'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/company-contracts/{$contract->id}")->assertOk();

        $this->assertDatabaseMissing('company_contracts', ['id' => $contract->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_kontrak_b2b']);
    }

    public function test_deleting_company_cascades_to_its_contracts(): void
    {
        $company = Company::create(['name' => 'PT Bubar']);
        $contract = $company->contracts()->create([
            'judul' => 'Kontrak Lama', 'mulai_at' => '2026-01-01', 'status' => 'aktif',
        ]);

        $company->delete();

        $this->assertDatabaseMissing('company_contracts', ['id' => $contract->id]);
    }

    public function test_company_index_includes_contracts(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Rekrut']);
        $company->contracts()->create(['judul' => 'Kontrak A', 'mulai_at' => '2026-01-01', 'status' => 'aktif']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/companies');

        $response->assertOk()->assertJsonCount(1, 'data.0.contracts')
            ->assertJsonPath('data.0.contracts.0.judul', 'Kontrak A');
    }
}
