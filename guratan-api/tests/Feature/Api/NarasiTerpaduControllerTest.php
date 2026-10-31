<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/**
 * ReportController::generateNarasiTerpadu/updateNarasiTerpadu + gating klien
 * di show()/pdf() - lihat CLAUDE.md "Narasi terpadu (laporan klien)" untuk
 * kenapa ini reversi sadar atas prinsip "LLM tidak live per-laporan".
 */
class NarasiTerpaduControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function completedReportFor(User $grafolog, ?User $client = null): PersonalityReport
    {
        $sindrom = $this->seedMinimalAspek(1);
        $sample = HandwritingSample::create([
            'user_id' => ($client ?? User::factory()->create(['role' => 'user']))->id,
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
                        ['kode' => '01', 'nama' => 'Aspek 1', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'narasi asli aspek 1'],
                    ],
                ]],
            ],
        ]);
    }

    private function fakeLlm(string $draftText): void
    {
        config([
            'services.llm.provider' => 'api',
            'services.llm.api_key' => 'test-key',
            'services.llm.endpoint' => 'https://api.anthropic.com/v1/messages',
            'services.llm.model' => 'claude-test-model',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $draftText]],
            ]),
        ]);
    }

    // --- generate ---

    public function test_owner_grafolog_can_generate_narasi_terpadu_draft(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $this->fakeLlm('Draft narasi terpadu hasil AI.');

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id']);

        $response->assertOk()
            ->assertJsonPath('narasi_terpadu', 'Draft narasi terpadu hasil AI.')
            ->assertJsonPath('narasi_bahasa', 'id')
            ->assertJsonPath('narasi_status', 'draft');

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'generate_narasi_terpadu', 'target_id' => $report->id, 'actor_user_id' => $grafolog->id,
        ]);
    }

    public function test_generate_includes_indikator_terkait_evidence_in_the_prompt(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $data = $report->data;
        $data['sindrom'][0]['aspek'][0]['indikator_terkait'] = [
            ['kode' => '01-1a', 'nama' => 'Indikator Contoh', 'keterangan' => 'Bukti tulisan tangan spesifik dari worksheet.'],
        ];
        $report->update(['data' => $data]);
        $this->fakeLlm('Draft.');

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertOk();

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];

            return str_contains($content, '01-1a')
                && str_contains($content, 'Bukti tulisan tangan spesifik dari worksheet.');
        });
    }

    public function test_generate_returns_clean_503_when_llm_unconfigured(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        // LLM_PROVIDER default 'none' di test env - sengaja tidak dikonfigurasi.

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id']);

        $response->assertStatus(503);
        $this->assertSame('belum_dibuat', $report->fresh()->narasi_status);
    }

    public function test_stranger_grafolog_cannot_generate_narasi_terpadu(): void
    {
        $owner = User::factory()->create(['role' => 'grafolog']);
        $stranger = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($owner);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertForbidden();
    }

    public function test_client_cannot_generate_narasi_terpadu(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertForbidden();
    }

    // --- update / finalize ---

    public function test_owner_grafolog_can_edit_and_finalize_narasi_terpadu(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $report->update(['narasi_terpadu' => 'draft lama', 'narasi_bahasa' => 'id', 'narasi_status' => 'draft']);

        $response = $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/narasi-terpadu", [
            'narasi_terpadu' => 'Versi final yang sudah ditinjau grafolog.',
            'bahasa' => 'id',
            'status' => 'final',
        ]);

        $response->assertOk()
            ->assertJsonPath('narasi_terpadu', 'Versi final yang sudah ditinjau grafolog.')
            ->assertJsonPath('narasi_status', 'final');

        $this->assertDatabaseCount('report_revisions', 1);
        $revision = $report->revisions()->first();
        $this->assertSame('edit_narasi_terpadu', $revision->jenis);
        $this->assertSame('draft lama', $revision->data['narasi_terpadu']);
        $this->assertSame('draft', $revision->data['narasi_status']);

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'edit_narasi_terpadu', 'target_id' => $report->id, 'actor_user_id' => $grafolog->id,
        ]);
    }

    public function test_client_cannot_edit_narasi_terpadu(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);

        $this->actingAs($client, 'sanctum')->patchJson("/api/reports/{$report->id}/narasi-terpadu", [
            'narasi_terpadu' => 'Tidak boleh.',
            'bahasa' => 'id',
            'status' => 'final',
        ])->assertForbidden();
    }

    // --- tampilan klien: narasi hanya bila final; rincian laporan tetap seperti biasa (tambahan, bukan pengganti) ---

    private function approved(PersonalityReport $report): void
    {
        $report->update(['review_status' => PersonalityReport::REVIEW_APPROVED]);
    }

    public function test_client_does_not_see_draft_narasi_but_still_sees_report(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'masih draft', 'narasi_bahasa' => 'id', 'narasi_status' => 'draft']);
        $this->approved($report);

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('narasi_terpadu', null)
            ->assertJsonPath('data.sindrom.0.aspek.0.kode', '01');
    }

    public function test_client_sees_narasi_terpadu_once_final(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'Laporan naratif final.', 'narasi_bahasa' => 'id', 'narasi_status' => 'final']);
        $this->approved($report);

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('narasi_terpadu', 'Laporan naratif final.')
            ->assertJsonPath('data.sindrom.0.aspek.0.kode', '01');
    }

    public function test_grafolog_sees_draft_narasi_regardless_of_status(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $report->update(['narasi_terpadu' => 'masih draft', 'narasi_status' => 'draft']);

        $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('narasi_terpadu', 'masih draft')
            ->assertJsonPath('data.sindrom.0.aspek.0.kode', '01');
    }

    public function test_client_cannot_download_narasi_pdf_before_final(): void
    {
        Storage::fake('local');
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'draft', 'narasi_bahasa' => 'id', 'narasi_status' => 'draft']);
        $this->approved($report);

        $this->actingAs($client, 'sanctum')->get("/api/reports/{$report->id}/pdf-narasi")->assertForbidden();
    }

    public function test_client_downloads_narasi_pdf_once_final_and_full_pdf_is_unchanged(): void
    {
        Storage::fake('local');
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'Laporan final.', 'narasi_bahasa' => 'id', 'narasi_status' => 'final']);
        $this->approved($report);

        $this->actingAs($client, 'sanctum')->get("/api/reports/{$report->id}/pdf-narasi")->assertOk();
        Storage::disk('local')->assertExists("reports/laporan-klien-{$report->id}.pdf");

        $this->actingAs($client, 'sanctum')->get("/api/reports/{$report->id}/pdf")->assertOk();
        $this->assertNotEmpty(Storage::disk('local')->files('reports'), 'PDF rincian tetap bisa diunduh klien');
        $this->assertCount(2, Storage::disk('local')->files('reports'));
    }

    public function test_narasi_pdf_requires_a_narasi(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        $this->actingAs($grafolog, 'sanctum')->get("/api/reports/{$report->id}/pdf-narasi")->assertStatus(422);
    }
}
