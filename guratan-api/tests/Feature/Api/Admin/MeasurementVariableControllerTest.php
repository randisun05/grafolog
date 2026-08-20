<?php

namespace Tests\Feature\Api\Admin;

use App\Models\MeasurementVariable;
use App\Models\MetodologiPenilaian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementVariableControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_measurement_variables(): void
    {
        $this->getJson('/api/admin/knowledge/measurement-variables')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/admin/knowledge/measurement-variables', ['kode' => '99', 'axis' => 'vertical', 'nama' => 'X'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_variable_and_it_defaults_to_master_methodology(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/measurement-variables', [
            'kode' => '99',
            'axis' => 'vertical',
            'nama' => 'Variabel Baru',
        ]);

        $response->assertCreated()->assertJsonPath('kode', '99');
        $master = MetodologiPenilaian::findByKode('master');
        $this->assertDatabaseHas('measurement_variable', ['kode' => '99', 'metodologi_id' => $master->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_variabel_ukur', 'actor_user_id' => $admin->id]);
    }

    public function test_duplicate_kode_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        MeasurementVariable::create(['kode' => '1', 'axis' => 'vertical', 'nama' => 'Ada']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/measurement-variables', [
            'kode' => '1',
            'axis' => 'vertical',
            'nama' => 'Duplikat',
        ])->assertUnprocessable()->assertJsonValidationErrors(['kode']);
    }

    public function test_invalid_axis_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/measurement-variables', [
            'kode' => '2',
            'axis' => 'diagonal',
            'nama' => 'X',
        ])->assertUnprocessable()->assertJsonValidationErrors(['axis']);
    }

    public function test_administrator_can_update_variable(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = MeasurementVariable::create(['kode' => '1', 'axis' => 'vertical', 'nama' => 'Lama']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/measurement-variables/{$variable->id}", [
            'nama' => 'Baru',
        ])->assertOk()->assertJsonPath('nama', 'Baru');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_variabel_ukur', 'actor_user_id' => $admin->id]);
    }

    public function test_deleting_variable_cascades_its_categories(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = MeasurementVariable::create(['kode' => '1', 'axis' => 'vertical', 'nama' => 'X']);
        $variable->kategori()->create(['kategori' => 'small', 'urutan' => 1]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/measurement-variables/{$variable->id}")
            ->assertOk();

        $this->assertDatabaseMissing('measurement_variable', ['id' => $variable->id]);
        $this->assertDatabaseMissing('measurement_category', ['variable_id' => $variable->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_variabel_ukur', 'actor_user_id' => $admin->id]);
    }

    public function test_index_eager_loads_categories_and_metodologi(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = MeasurementVariable::create(['kode' => '1', 'axis' => 'vertical', 'nama' => 'X']);
        $variable->kategori()->create(['kategori' => 'small', 'urutan' => 1]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/measurement-variables')
            ->assertOk()
            ->assertJsonCount(1, '0.kategori');
    }
}
