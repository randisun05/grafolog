<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/**
 * ReportController::updateNarasi/revisions/showRevision - lihat CLAUDE.md
 * untuk kenapa override manual ditulis langsung ke JSON `data` (bukan tabel
 * terpisah) dan kenapa dihapus otomatis saat laporan dikoreksi ulang
 * (ScoringCorrectionTest::test_correcting_clears_manual_narasi_override...).
 */
class ReportRevisionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function completedReportFor(User $grafolog): PersonalityReport
    {
        $sindrom = $this->seedMinimalAspek(1);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
            'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $sindrom->id,
                    'kode_romawi' => $sindrom->kode_romawi,
                    'nama' => $sindrom->nama,
                    'polaritas' => 'HIJAU',
                    'catatan_polaritas' => null,
                    'rata_rata_skor' => 7.0,
                    'band_label_rata_rata' => 'Nilai Tinggi',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek 1', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'narasi asli'],
                    ],
                ]],
            ],
        ]);
    }

    public function test_owner_grafolog_can_override_narasi(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        $response = $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", [
            'narasi' => 'Teks yang sudah ditinjau ulang oleh grafolog.',
            'catatan' => 'Klien minta klarifikasi tambahan.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sindrom.0.aspek.0.narasi', 'Teks yang sudah ditinjau ulang oleh grafolog.')
            ->assertJsonPath('data.sindrom.0.aspek.0.narasi_diedit_manual', true);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'edit_narasi_laporan', 'target_id' => $report->id, 'actor_user_id' => $grafolog->id]);
    }

    public function test_override_snapshots_previous_version(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", [
            'narasi' => 'Versi baru.',
        ])->assertOk();

        $this->assertDatabaseCount('report_revisions', 1);
        $revision = $report->revisions()->first();
        $this->assertSame('edit_manual', $revision->jenis);
        $this->assertSame('narasi asli', $revision->data['sindrom'][0]['aspek'][0]['narasi']);
    }

    public function test_unknown_aspek_kode_returns_not_found_without_writing_revision(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/99/narasi", [
            'narasi' => 'Tidak akan tersimpan.',
        ])->assertNotFound();

        $this->assertDatabaseCount('report_revisions', 0);
    }

    public function test_non_grafolog_cannot_override_narasi(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog);

        $this->actingAs($client, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", [
            'narasi' => 'Tidak boleh.',
        ])->assertForbidden();
    }

    public function test_stranger_grafolog_cannot_override_narasi(): void
    {
        $owner = User::factory()->create(['role' => 'grafolog']);
        $stranger = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($owner);

        $this->actingAs($stranger, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", [
            'narasi' => 'Tidak boleh.',
        ])->assertForbidden();
    }

    public function test_owner_can_list_revisions(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'v2'])->assertOk();

        $response = $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}/revisions");

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.jenis', 'edit_manual');
    }

    public function test_owner_can_view_a_specific_revision(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'v2'])->assertOk();
        $revisionId = $report->revisions()->first()->id;

        $response = $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}/revisions/{$revisionId}");

        $response->assertOk()->assertJsonPath('data.sindrom.0.aspek.0.narasi', 'narasi asli');
    }

    public function test_client_owner_can_view_revisions_but_stranger_cannot(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        $report = PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => ['sindrom' => [['id' => $sindrom->id, 'kode_romawi' => $sindrom->kode_romawi, 'nama' => $sindrom->nama, 'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => 7.0, 'band_label_rata_rata' => 'Nilai Tinggi', 'aspek' => [['kode' => '01', 'nama' => 'Aspek 1', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'x']]]]],
        ]);

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}/revisions")->assertOk();

        $stranger = User::factory()->create();
        $this->actingAs($stranger, 'sanctum')->getJson("/api/reports/{$report->id}/revisions")->assertForbidden();
    }
}
