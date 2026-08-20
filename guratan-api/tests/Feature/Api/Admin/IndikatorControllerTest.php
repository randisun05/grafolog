<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function aspek(): Aspek
    {
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);

        return Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);
    }

    public function test_guest_cannot_manage_indikator(): void
    {
        $this->getJson('/api/admin/knowledge/indikator')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $aspek = $this->aspek();

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/admin/knowledge/indikator', ['kode' => "01-11'", 'aspek_id' => $aspek->id, 'posisi' => 1, 'nama' => 'X'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_indikator(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/indikator', [
            'kode' => "01-11'",
            'aspek_id' => $aspek->id,
            'posisi' => 1,
            'varian' => null,
            'nama' => 'Indikator Baru',
        ]);

        $response->assertCreated()
            ->assertJsonPath('kode', "01-11'")
            ->assertJsonPath('aspek.kode', '01');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_indikator', 'actor_user_id' => $admin->id]);
    }

    public function test_posisi_out_of_range_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/indikator', [
            'kode' => "01-11'",
            'aspek_id' => $aspek->id,
            'posisi' => 11,
            'nama' => 'X',
        ])->assertUnprocessable()->assertJsonValidationErrors(['posisi']);
    }

    public function test_duplicate_kode_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Ada']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/indikator', [
            'kode' => "01-1'",
            'aspek_id' => $aspek->id,
            'posisi' => 1,
            'nama' => 'Duplikat',
        ])->assertUnprocessable()->assertJsonValidationErrors(['kode']);
    }

    public function test_administrator_can_update_indikator(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $indikator = Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Lama']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/indikator/{$indikator->id}", [
            'nama' => 'Baru',
            'varian' => 'a',
        ])->assertOk()->assertJsonPath('nama', 'Baru')->assertJsonPath('varian', 'a');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_indikator', 'actor_user_id' => $admin->id]);
    }

    public function test_administrator_can_delete_indikator(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $indikator = Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'X']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/indikator/{$indikator->id}")
            ->assertOk();

        $this->assertDatabaseMissing('indikator', ['id' => $indikator->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_indikator', 'actor_user_id' => $admin->id]);
    }

    public function test_index_is_paginated_and_searchable(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Keras kepala']);
        Indikator::create(['kode' => "01-2'", 'posisi' => 2, 'aspek_id' => $aspek->id, 'nama' => 'Lain sama sekali']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/indikator?search=keras');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'Keras kepala');
    }

    public function test_index_filters_by_aspek_id(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek1 = $this->aspek();
        $sindrom2 = Sindrom::create(['kode_romawi' => 'II', 'nama' => 'Intellect', 'polaritas_inferred' => 'HIJAU']);
        $aspek2 = Aspek::create(['kode' => '06', 'sindrom_id' => $sindrom2->id, 'nama' => 'Thinking effectiveness']);
        Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek1->id, 'nama' => 'A']);
        Indikator::create(['kode' => "06-1'", 'posisi' => 1, 'aspek_id' => $aspek2->id, 'nama' => 'B']);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/knowledge/indikator?aspek_id={$aspek2->id}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'B');
    }
}
