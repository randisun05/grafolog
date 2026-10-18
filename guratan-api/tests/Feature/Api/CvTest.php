<?php

namespace Tests\Feature\Api;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\ReportValidation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Manajemen variabel CV oleh admin + pengisian + visibilitas per peran (2026-10-19). */
class CvTest extends TestCase
{
    use RefreshDatabase;

    private function field(CvGroup $g, string $label, string $type = 'text', string $vis = 'all', array $extra = []): CvField
    {
        return CvField::create($extra + [
            'cv_group_id' => $g->id, 'key' => str($label)->slug('_')->toString(), 'label' => $label,
            'type' => $type, 'applies_to' => $vis,
        ]);
    }

    public function test_admin_manages_groups_and_fields_with_types_and_tags(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $gid = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/cv/groups', ['name' => 'Orang Tua'])->assertCreated()->json('id');
        $f = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/cv/fields', [
            'cv_group_id' => $gid, 'label' => 'Pekerjaan Ayah', 'type' => 'select', 'options' => ['PNS', 'Swasta'], 'applies_to' => 'client', 'is_hidden' => true,
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

    public function test_applies_to_and_hidden_control_who_sees_what(): void
    {
        $g = CvGroup::create(['name' => 'Umum']);
        $this->field($g, 'Semua', 'text', 'all');
        $this->field($g, 'Khusus Klien', 'text', 'client');
        $this->field($g, 'Khusus Grafolog', 'text', 'grafolog');
        $this->field($g, 'Rahasia', 'text', 'all', ['is_hidden' => true]);

        $company = Company::create(['name' => 'PT Uji']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $outsiderHr = User::factory()->create(['role' => 'hr', 'company_id' => Company::create(['name' => 'PT Lain'])->id]);
        $candidate = User::factory()->create(['role' => 'user', 'company_id' => $company->id]);
        CandidateProfile::create(['user_id' => $candidate->id, 'company_id' => $company->id, 'created_by' => $hr->id]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $otherGrafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        HandwritingSample::create(['user_id' => $candidate->id, 'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'pending']);
        $admin = User::factory()->create(['role' => 'administrator']);

        $keys = fn (User $viewer, User $subject) => collect($this->actingAs($viewer, 'sanctum')->getJson("/api/cv/users/{$subject->id}")->assertOk()->json('groups.0.fields'))->pluck('key')->all();
        $put = fn (User $viewer, User $subject, array $v) => $this->actingAs($viewer, 'sanctum')->putJson("/api/cv/users/{$subject->id}", ['values' => $v]);

        // CV kandidat (klien): variabel 'client'/'all'; HR mengisi tapi tidak bisa menyentuh yang tersembunyi.
        $put($hr, $candidate, ['semua' => 'a', 'khusus_klien' => 'b', 'khusus_grafolog' => 'x', 'rahasia' => 'x'])->assertOk();
        $this->assertDatabaseMissing('cv_values', ['value' => 'x']);
        $this->actingAs($candidate, 'sanctum')->putJson('/api/cv', ['values' => ['rahasia' => 'r']])->assertOk();
        $this->assertSame(['semua', 'khusus_klien', 'rahasia'], $keys($candidate, $candidate));
        $this->assertSame(['semua', 'khusus_klien'], $keys($hr, $candidate));
        $this->assertSame(['semua', 'khusus_klien'], $keys($grafolog, $candidate));
        $this->assertSame(['semua', 'khusus_klien', 'rahasia'], $keys($admin, $candidate));
        $this->actingAs($outsiderHr, 'sanctum')->getJson("/api/cv/users/{$candidate->id}")->assertForbidden();
        $this->actingAs($otherGrafolog, 'sanctum')->getJson("/api/cv/users/{$candidate->id}")->assertForbidden();
        $put($grafolog, $candidate, ['semua' => 'z'])->assertForbidden();

        // CV grafolog: variabel 'grafolog'/'all'; klien & HR yang diperiksa grafolog ini boleh melihat (tanpa tersembunyi).
        $this->actingAs($grafolog, 'sanctum')->putJson('/api/cv', ['values' => ['khusus_grafolog' => 'g', 'rahasia' => 'r2']])->assertOk();
        $this->assertSame(['semua', 'khusus_grafolog', 'rahasia'], $keys($grafolog, $grafolog));
        $this->assertSame(['semua', 'khusus_grafolog'], $keys($candidate, $grafolog));
        $this->assertSame(['semua', 'khusus_grafolog'], $keys($hr, $grafolog));
        $this->assertSame(['semua', 'khusus_grafolog', 'rahasia'], $keys($admin, $grafolog));
        $this->actingAs($outsiderHr, 'sanctum')->getJson("/api/cv/users/{$grafolog->id}")->assertForbidden();
        $this->actingAs($otherGrafolog, 'sanctum')->getJson("/api/cv/users/{$grafolog->id}")->assertForbidden();
        $stranger = User::factory()->create(['role' => 'user']);
        $this->actingAs($stranger, 'sanctum')->getJson("/api/cv/users/{$grafolog->id}")->assertForbidden();
        $put($candidate, $grafolog, ['semua' => 'z'])->assertForbidden();
    }

    public function test_photo_and_file_upload_visibility_and_peer_access(): void
    {
        Storage::fake('local');
        $g = CvGroup::create(['name' => 'Berkas']);
        $this->field($g, 'Foto', 'photo', 'all');
        $this->field($g, 'Dokumen', 'file', 'grafolog', ['is_hidden' => true]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $client = User::factory()->create(['role' => 'user']);
        HandwritingSample::create(['user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => 'master', 'status' => 'pending']);

        $this->actingAs($grafolog, 'sanctum')->post('/api/cv/files/foto', ['file' => UploadedFile::fake()->image('a.jpg', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('groups.0.fields.0.value.name', 'a.jpg');
        $this->actingAs($grafolog, 'sanctum')->post('/api/cv/files/foto', ['file' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($grafolog, 'sanctum')->post('/api/cv/files/dokumen', ['file' => UploadedFile::fake()->create('cv.docx', 10)], ['Accept' => 'application/json'])->assertOk();

        // Klien yang diperiksa: melihat foto (bukan dokumen tersembunyi) dan boleh mengunduhnya.
        $this->actingAs($client, 'sanctum')->getJson("/api/cv/users/{$grafolog->id}")->assertOk()->assertJsonCount(1, 'groups.0.fields');
        $this->actingAs($client, 'sanctum')->get("/api/cv/users/{$grafolog->id}/files/foto")->assertOk();
        $this->actingAs($client, 'sanctum')->get("/api/cv/users/{$grafolog->id}/files/dokumen")->assertNotFound();
        $stranger = User::factory()->create(['role' => 'user']);
        $this->actingAs($stranger, 'sanctum')->get("/api/cv/users/{$grafolog->id}/files/foto")->assertForbidden();
        $this->actingAs($client, 'sanctum')->post('/api/cv/files/foto', ['file' => UploadedFile::fake()->image('b.jpg', 200, 200)], ['Accept' => 'application/json'])->assertOk(); // foto milik klien sendiri (applies all)

        // Supervisor (peer): terhubung lewat penugasan supervisi.
        $peer = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $this->actingAs($peer, 'sanctum')->getJson("/api/cv/users/{$grafolog->id}")->assertForbidden();
        $sample = HandwritingSample::first();
        $report = PersonalityReport::create(['sample_id' => $sample->id, 'tier' => 'master', 'status' => 'completed']);
        ReportValidation::create(['report_id' => $report->id, 'requester_id' => $grafolog->id, 'validator_id' => $peer->id, 'status' => 'menunggu', 'fee' => 0, 'deadline_days' => 3, 'due_at' => now()->addDays(3)]);
        $this->actingAs($peer, 'sanctum')->getJson("/api/cv/users/{$grafolog->id}")->assertOk();
        $this->actingAs($grafolog, 'sanctum')->getJson("/api/cv/users/{$peer->id}")->assertOk();

        // Hapus berkas.
        $this->actingAs($grafolog, 'sanctum')->deleteJson('/api/cv/files/foto')->assertOk()->assertJsonPath('groups.0.fields.0.value', null);
    }
}
