<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SindromControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_sindrom(): void
    {
        $this->getJson('/api/admin/knowledge/sindrom')->assertUnauthorized();
        $this->postJson('/api/admin/knowledge/sindrom', [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/admin/knowledge/sindrom', ['kode_romawi' => 'IX', 'nama' => 'X', 'polaritas_inferred' => 'HIJAU'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_sindrom(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/sindrom', [
            'kode_romawi' => 'IX',
            'nama' => 'Sindrom Baru',
            'polaritas_inferred' => 'HIJAU',
        ]);

        $response->assertCreated()->assertJsonPath('kode_romawi', 'IX');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_sindrom', 'actor_user_id' => $admin->id]);
    }

    public function test_duplicate_kode_romawi_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Ada', 'polaritas_inferred' => 'HIJAU']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/knowledge/sindrom', [
            'kode_romawi' => 'I',
            'nama' => 'Duplikat',
            'polaritas_inferred' => 'HIJAU',
        ])->assertUnprocessable()->assertJsonValidationErrors(['kode_romawi']);
    }

    public function test_administrator_can_update_sindrom(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Lama', 'polaritas_inferred' => 'HIJAU']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/sindrom/{$sindrom->id}", [
            'nama' => 'Baru',
        ]);

        $response->assertOk()->assertJsonPath('nama', 'Baru');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_sindrom', 'actor_user_id' => $admin->id]);
    }

    public function test_updating_kode_romawi_can_keep_its_own_value(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Lama', 'polaritas_inferred' => 'HIJAU']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/sindrom/{$sindrom->id}", [
            'kode_romawi' => 'I',
            'nama' => 'Baru',
        ])->assertOk();
    }

    public function test_cannot_delete_sindrom_with_related_aspek(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Ada Aspek', 'polaritas_inferred' => 'HIJAU']);
        Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Aspek 1']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/sindrom/{$sindrom->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('sindrom', ['id' => $sindrom->id]);
    }

    public function test_administrator_can_delete_sindrom_without_aspek(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = Sindrom::create(['kode_romawi' => 'IX', 'nama' => 'Kosong', 'polaritas_inferred' => 'HIJAU']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/sindrom/{$sindrom->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sindrom', ['id' => $sindrom->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_sindrom', 'actor_user_id' => $admin->id]);
    }

    public function test_index_includes_aspek_count(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Ada Aspek', 'polaritas_inferred' => 'HIJAU']);
        Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Aspek 1']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/sindrom')
            ->assertOk()->assertJsonPath('0.aspek_count', 1);
    }
}
