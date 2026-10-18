<?php

namespace Tests\Feature\Api;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\HandwritingSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Manajemen variabel CV oleh admin + pengisian + visibilitas per peran (2026-10-19). */
class CvTest extends TestCase
{
    use RefreshDatabase;

    private function field(CvGroup $g, string $label, string $type = 'text', string $vis = 'all', array $extra = []): CvField
    {
        return CvField::create($extra + [
            'cv_group_id' => $g->id, 'key' => str($label)->slug('_')->toString(), 'label' => $label,
            'type' => $type, 'visible_to' => $vis,
        ]);
    }

    public function test_admin_manages_groups_and_fields_with_types_and_tags(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $gid = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/cv/groups', ['name' => 'Orang Tua'])->assertCreated()->json('id');
        $f = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/cv/fields', [
            'cv_group_id' => $gid, 'label' => 'Pekerjaan Ayah', 'type' => 'select', 'options' => ['PNS', 'Swasta'], 'visible_to' => 'grafolog',
        ])->assertCreated()->assertJsonPath('key', 'pekerjaan_ayah')->json();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/cv/fields', ['cv_group_id' => $gid, 'label' => 'X', 'type' => 'select'])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/cv/fields', ['cv_group_id' => $gid, 'label' => 'Pekerjaan Ayah', 'type' => 'text'])->assertCreated()->assertJsonPath('key', 'pekerjaan_ayah_2');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/cv')->assertOk()->assertJsonPath('0.fields_count', 2);

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/cv')->assertForbidden();

        // Setelah ada data: tipe terkunci, hapus = nonaktif.
        $this->actingAs($user, 'sanctum')->putJson('/api/cv', ['values' => ['pekerjaan_ayah' => 'PNS']])->assertOk();
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/cv/fields/{$f['id']}", ['type' => 'text'])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/cv/fields/{$f['id']}")->assertNoContent();
        $this->assertDatabaseHas('cv_fields', ['id' => $f['id'], 'is_active' => false]);
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/cv/groups/{$gid}")->assertStatus(422);
    }

    public function test_owner_fills_typed_values_with_validation(): void
    {
        $g = CvGroup::create(['name' => 'Profil']);
        $this->field($g, 'Umur', 'number');
        $this->field($g, 'Lahir', 'date');
        $this->field($g, 'Menikah', 'boolean');
        $this->field($g, 'Hobi', 'select', 'all', ['options' => ['Baca', 'Olahraga']]);
        $this->field($g, 'Nama Lengkap', 'text', 'all', ['required' => true]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')->putJson('/api/cv', ['values' => ['umur' => 'abc']])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->putJson('/api/cv', ['values' => ['hobi' => 'Tidur']])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->putJson('/api/cv', ['values' => ['nama_lengkap' => '']])->assertStatus(422);
        $res = $this->actingAs($user, 'sanctum')->putJson('/api/cv', ['values' => [
            'umur' => 27, 'lahir' => '1999-01-02', 'menikah' => true, 'hobi' => 'Baca', 'nama_lengkap' => 'Budi', 'tidak_ada' => 'x',
        ]])->assertOk();
        $vals = collect($res->json('groups.0.fields'))->pluck('value', 'key');
        $this->assertSame(27, $vals['umur']);
        $this->assertTrue($vals['menikah']);
        $this->assertSame('Baca', $vals['hobi']);
        $this->assertDatabaseMissing('cv_values', ['value' => 'x']);
    }

    public function test_visibility_tags_filter_by_viewer_role(): void
    {
        $g = CvGroup::create(['name' => 'Keluarga']);
        $this->field($g, 'Umum', 'text', 'all');
        $this->field($g, 'Untuk Klien', 'text', 'client');
        $this->field($g, 'Untuk Grafolog', 'text', 'grafolog');

        $company = Company::create(['name' => 'PT Uji']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $outsiderHr = User::factory()->create(['role' => 'hr', 'company_id' => Company::create(['name' => 'PT Lain'])->id]);
        $candidate = User::factory()->create(['role' => 'user', 'company_id' => $company->id]);
        CandidateProfile::create(['user_id' => $candidate->id, 'company_id' => $company->id, 'created_by' => $hr->id]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $otherGrafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        HandwritingSample::create(['user_id' => $candidate->id, 'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'pending']);
        $admin = User::factory()->create(['role' => 'administrator']);

        // HR mengisi untuk kandidatnya.
        $this->actingAs($hr, 'sanctum')->putJson("/api/cv/users/{$candidate->id}", ['values' => [
            'umum' => 'a', 'untuk_klien' => 'b', 'untuk_grafolog' => 'c',
        ]])->assertOk();

        $keys = fn (User $u) => collect($this->actingAs($u, 'sanctum')->getJson("/api/cv/users/{$candidate->id}")->assertOk()->json('groups.0.fields'))->pluck('key')->all();
        $this->assertSame(['umum', 'untuk_klien', 'untuk_grafolog'], $keys($candidate));
        $this->assertSame(['umum', 'untuk_klien'], $keys($hr));
        $this->assertSame(['umum', 'untuk_grafolog'], $keys($grafolog));
        $this->assertSame(['umum', 'untuk_klien', 'untuk_grafolog'], $keys($admin));

        $this->actingAs($outsiderHr, 'sanctum')->getJson("/api/cv/users/{$candidate->id}")->assertForbidden();
        $this->actingAs($otherGrafolog, 'sanctum')->getJson("/api/cv/users/{$candidate->id}")->assertForbidden();
        // Grafolog & admin hanya membaca.
        $this->actingAs($grafolog, 'sanctum')->putJson("/api/cv/users/{$candidate->id}", ['values' => ['umum' => 'z']])->assertForbidden();
        $this->actingAs($outsiderHr, 'sanctum')->putJson("/api/cv/users/{$candidate->id}", ['values' => ['umum' => 'z']])->assertForbidden();
    }
}
