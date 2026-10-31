<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\KombinasiTemuan;
use App\Models\Sindrom;
use App\Models\Topik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopikControllerTest extends TestCase
{
    use RefreshDatabase;

    private function aspek(): Aspek
    {
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);

        return Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);
    }

    public function test_guest_cannot_manage_topik(): void
    {
        $this->getJson('/api/admin/knowledge/topik')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/knowledge/topik')->assertForbidden();
    }

    public function test_administrator_can_create_list_update_delete_topik(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/topik', [
            'nama' => 'Karier', 'deskripsi' => 'Aspek terkait pekerjaan.',
        ]);
        $create->assertCreated()->assertJsonPath('nama', 'Karier');
        $id = $create->json('id');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_topik', 'actor_user_id' => $admin->id]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/topik')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.aspek_count', 0);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/topik/{$id}", ['nama' => 'Karier & Pekerjaan'])
            ->assertOk()->assertJsonPath('nama', 'Karier & Pekerjaan');

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/topik/{$id}")->assertOk();
        $this->assertDatabaseMissing('topik', ['id' => $id]);
    }

    public function test_topik_nama_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Topik::create(['nama' => 'Karier']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/topik', ['nama' => 'Karier'])
            ->assertStatus(422)->assertJsonValidationErrors(['nama']);
    }

    public function test_updating_topik_does_not_conflict_with_its_own_name(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $topik = Topik::create(['nama' => 'Karier']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/topik/{$topik->id}", ['nama' => 'Karier'])
            ->assertOk();
    }

    public function test_administrator_can_sync_topik_to_aspek(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $karier = Topik::create(['nama' => 'Karier']);
        $percintaan = Topik::create(['nama' => 'Percintaan']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/aspek/{$aspek->id}/topik", [
            'topik_ids' => [$karier->id, $percintaan->id],
        ]);

        $response->assertOk()->assertJsonCount(2, 'topik');
        $this->assertDatabaseCount('aspek_topik', 2);

        // sync ulang dengan set lebih kecil -> baris lama dilepas, bukan ditambah.
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/aspek/{$aspek->id}/topik", [
            'topik_ids' => [$karier->id],
        ])->assertOk()->assertJsonCount(1, 'topik');
        $this->assertDatabaseCount('aspek_topik', 1);
    }

    public function test_administrator_can_sync_topik_to_kombinasi_temuan(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $temuan = KombinasiTemuan::create(['nama' => 'Pola', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);
        $karier = Topik::create(['nama' => 'Karier']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/kombinasi/{$temuan->id}/topik", [
            'topik_ids' => [$karier->id],
        ])->assertOk()->assertJsonCount(1, 'topik');

        $this->assertDatabaseCount('kombinasi_temuan_topik', 1);
    }

    public function test_sync_topik_rejects_unknown_topik_id(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/aspek/{$aspek->id}/topik", [
            'topik_ids' => [999],
        ])->assertStatus(422)->assertJsonValidationErrors(['topik_ids.0']);
    }

    public function test_deleting_topik_removes_its_tags_but_not_the_aspek(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $aspek = $this->aspek();
        $topik = Topik::create(['nama' => 'Karier']);
        $aspek->topik()->attach($topik->id);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/topik/{$topik->id}")->assertOk();

        $this->assertDatabaseCount('aspek_topik', 0);
        $this->assertDatabaseHas('aspek', ['id' => $aspek->id]);
    }
}
