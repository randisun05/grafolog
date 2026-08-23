<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateNarasiTerpaduJob;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        $this->fakeLlmSequence([$draftText]);
    }

    /**
     * Http::fake() merger stub baru DI BELAKANG stub lama untuk pola URL
     * yang sama, dan resolusinya ambil kecocokan PERTAMA - jadi memanggil
     * fakeLlm() dua kali dalam 1 test untuk 2 draft berbeda TIDAK akan
     * pernah dapat draft ke-2 (stub pertama selalu menang). Test yang perlu
     * >1 respons berbeda (regenerate/dedup-guard) harus pakai ini,
     * Http::sequence(), bukan panggil fakeLlm() berulang.
     *
     * @param  array<int, string>  $draftTexts
     */
    private function fakeLlmSequence(array $draftTexts): void
    {
        config([
            'services.llm.provider' => 'api',
            'services.llm.api_key' => 'test-key',
            'services.llm.endpoint' => 'https://api.anthropic.com/v1/messages',
            'services.llm.model' => 'claude-test-model',
        ]);

        $sequence = Http::sequence();
        foreach ($draftTexts as $draftText) {
            $sequence->push(['content' => [['type' => 'text', 'text' => $draftText]]]);
        }

        Http::fake(['api.anthropic.com/*' => $sequence]);
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

    public function test_generate_job_records_error_and_reverts_status_when_llm_unconfigured(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        // LLM_PROVIDER default 'none' di test env - sengaja tidak dikonfigurasi.
        // QUEUE_CONNECTION=sync di phpunit.xml - job langsung jalan inline,
        // jadi response POST ini sudah mencerminkan hasil AKHIR job, bukan
        // status 'generating' sesaat (itu dites terpisah lewat Queue::fake()
        // di bawah karena sync queue tidak menyisakan jendela untuk diamati).

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id']);

        $response->assertOk();
        $fresh = $report->fresh();
        $this->assertSame('belum_dibuat', $fresh->narasi_status);
        $this->assertNotNull($fresh->narasi_generation_error);
        $this->assertNull($fresh->narasi_terpadu);
    }

    public function test_generate_sets_status_to_generating_and_dispatches_job(): void
    {
        Queue::fake();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id']);

        $response->assertOk()->assertJsonPath('narasi_status', 'generating');

        Queue::assertPushed(GenerateNarasiTerpaduJob::class, fn ($job) => $job->reportId === $report->id && $job->bahasa === 'id');
    }

    public function test_regenerate_with_unchanged_data_is_rejected_without_force(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $this->fakeLlm('Draft pertama.');
        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertOk();

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id']);

        $response->assertStatus(409);
        $this->assertSame('Draft pertama.', $report->fresh()->narasi_terpadu);
    }

    public function test_regenerate_with_force_bypasses_dedup_guard(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $this->fakeLlmSequence(['Draft pertama.', 'Draft kedua, ditulis ulang.']);
        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertOk();

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id', 'force' => true]);

        $response->assertOk()->assertJsonPath('narasi_terpadu', 'Draft kedua, ditulis ulang.');
    }

    public function test_regenerate_after_score_correction_is_allowed_without_force(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $this->fakeLlmSequence(['Draft pertama.', 'Draft setelah koreksi skor.']);
        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertOk();

        // Skor berubah (mis. lewat ScoringController::correct) -> data.sindrom
        // beda -> hash beda -> dedup-guard TIDAK menahan generate berikutnya
        // walau tanpa force.
        $data = $report->fresh()->data;
        $data['sindrom'][0]['aspek'][0]['skor'] = 3;
        $report->update(['data' => $data]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id']);

        $response->assertOk()->assertJsonPath('narasi_terpadu', 'Draft setelah koreksi skor.');
    }

    public function test_generate_rejected_while_already_generating(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $report->update(['narasi_status' => 'generating']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id'])
            ->assertStatus(409);
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

    /**
     * Guard biaya AI - throttle:20,60 KHUSUS endpoint ini (lebih ketat dari
     * throttle:60,1 grup umum), lihat CLAUDE.md "Guard biaya AI". Queue::fake()
     * supaya 20 percobaan ini tidak benar-benar mengeksekusi job (QUEUE_CONNECTION
     * sync di phpunit.xml akan menjalankannya inline kalau tidak di-fake) - yang
     * diuji di sini murni perilaku throttle middleware, bukan hasil generate-nya.
     */
    public function test_generate_endpoint_is_rate_limited_separately_from_general_throttle(): void
    {
        Queue::fake();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($grafolog, 'sanctum')
                ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id', 'force' => true]);
        }

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/reports/{$report->id}/narasi-terpadu/generate", ['bahasa' => 'id', 'force' => true]);

        $response->assertStatus(429);
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

    // --- client-facing gating on show()/pdf() ---

    public function test_client_cannot_view_report_before_narasi_final(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'draft', 'narasi_status' => 'draft']);

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
    }

    public function test_client_sees_only_narasi_terpadu_once_final(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'Laporan naratif final.', 'narasi_bahasa' => 'id', 'narasi_status' => 'final']);

        $response = $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('narasi_terpadu', 'Laporan naratif final.')
            ->assertJsonMissing(['data'])
            ->assertJsonMissing(['aspek_scores']);
    }

    public function test_grafolog_sees_full_breakdown_and_draft_narasi_regardless_of_status(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);
        $report->update(['narasi_terpadu' => 'masih draft', 'narasi_status' => 'draft']);

        $response = $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('narasi_terpadu', 'masih draft')
            ->assertJsonPath('data.sindrom.0.aspek.0.kode', '01');
    }

    public function test_client_cannot_download_pdf_before_narasi_final(): void
    {
        Storage::fake('local');
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'draft', 'narasi_status' => 'draft']);

        $this->actingAs($client, 'sanctum')->get("/api/reports/{$report->id}/pdf")->assertForbidden();
    }

    public function test_client_downloads_klien_pdf_variant_once_final(): void
    {
        Storage::fake('local');
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportFor($grafolog, $client);
        $report->update(['narasi_terpadu' => 'Laporan final.', 'narasi_bahasa' => 'id', 'narasi_status' => 'final']);

        $this->actingAs($client, 'sanctum')->get("/api/reports/{$report->id}/pdf")->assertOk();

        Storage::disk('local')->assertExists("reports/laporan-klien-{$report->id}.pdf");
        Storage::disk('local')->assertMissing("reports/laporan-{$report->id}.pdf");
    }

    public function test_grafolog_downloads_internal_pdf_variant(): void
    {
        Storage::fake('local');
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->completedReportFor($grafolog);

        $this->actingAs($grafolog, 'sanctum')->get("/api/reports/{$report->id}/pdf")->assertOk();

        Storage::disk('local')->assertExists("reports/laporan-{$report->id}.pdf");
        Storage::disk('local')->assertMissing("reports/laporan-klien-{$report->id}.pdf");
    }
}
