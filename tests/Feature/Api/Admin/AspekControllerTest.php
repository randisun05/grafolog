<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AspekControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sindrom(): Sindrom
    {
        return Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);
    }

    public function test_guest_cannot_manage_aspek(): void
    {
        $this->getJson('/api/admin/knowledge/aspek')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sindrom = $this->sindrom();

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/admin/knowledge/aspek', ['kode' => '41', 'sindrom_id' => $sindrom->id, 'nama' => 'X'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_aspek(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = $this->sindrom();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/aspek', [
            'kode' => '41',
            'sindrom_id' => $sindrom->id,
            'nama' => 'Aspek Baru',
            'keterangan_umum' => 'Ringkasan.',
            'narasi_high' => 'Narasi tinggi.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('kode', '41')
            ->assertJsonPath('sindrom.kode_romawi', 'I');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_aspek', 'actor_user_id' => $admin->id]);
    }

    public function test_duplicate_kode_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = $this->sindrom();
        Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Ada']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/aspek', [
            'kode' => '01',
            'sindrom_id' => $sindrom->id,
            'nama' => 'Duplikat',
        ])->assertUnprocessable()->assertJsonValidationErrors(['kode']);
    }

    public function test_unknown_sindrom_id_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/aspek', [
            'kode' => '41',
            'sindrom_id' => 9999,
            'nama' => 'X',
        ])->assertUnprocessable()->assertJsonValidationErrors(['sindrom_id']);
    }

    public function test_administrator_can_update_aspek_narasi(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = $this->sindrom();
        $aspek = Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Lama']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/aspek/{$aspek->id}", [
            'narasi_very_high' => 'Narasi very high baru.',
        ]);

        $response->assertOk()->assertJsonPath('narasi_very_high', 'Narasi very high baru.');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_aspek', 'actor_user_id' => $admin->id]);
    }

    public function test_cannot_delete_aspek_with_related_indikator(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = $this->sindrom();
        $aspek = Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Ada Indikator']);
        Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Indikator 1']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/aspek/{$aspek->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('aspek', ['id' => $aspek->id]);
    }

    public function test_administrator_can_delete_aspek_without_indikator(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = $this->sindrom();
        $aspek = Aspek::create(['kode' => '41', 'sindrom_id' => $sindrom->id, 'nama' => 'Kosong']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/aspek/{$aspek->id}")
            ->assertOk();

        $this->assertDatabaseMissing('aspek', ['id' => $aspek->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_aspek', 'actor_user_id' => $admin->id]);
    }

    public function test_index_includes_sindrom_and_indikator_count(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = $this->sindrom();
        $aspek = Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Ada Indikator']);
        Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Indikator 1']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/aspek')
            ->assertOk()
            ->assertJsonPath('0.indikator_count', 1)
            ->assertJsonPath('0.sindrom.kode_romawi', 'I');
    }
}
