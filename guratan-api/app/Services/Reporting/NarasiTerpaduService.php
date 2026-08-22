<?php

namespace App\Services\Reporting;

use App\Models\PersonalityReport;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Merangkai draft narasi terpadu (deskriptif, mengalir dalam 1 bahasa) dari
 * SATU laporan utuh - berbeda dari NarasiCacheService/LlmProviderInterface
 * yang sengaja dibatasi per-aspek-per-level supaya bisa di-cache permanen
 * (lihat docblock LlmProviderInterface). Kombinasi 40 skor tiap klien unik,
 * jadi hasil di sini TIDAK bisa di-cache - ini genuinely 1 call live per
 * generate.
 *
 * Ini REVERSI SADAR atas prinsip "LLM tidak live per-user" yang sebelumnya
 * dikunci (lihat root CLAUDE.md), dikonfirmasi user 2026-08-22 lewat
 * AskUserQuestion: draft di sini TIDAK PERNAH langsung dikirim ke klien -
 * grafolog wajib review/edit lalu menandai `narasi_status = final` dulu
 * (lihat ReportController::generateNarasiTerpadu/updateNarasiTerpadu) baru
 * klien bisa melihatnya.
 */
class NarasiTerpaduService
{
    public function generateDraft(PersonalityReport $report, string $bahasa): string
    {
        $this->ensureConfigured();

        $ringkasan = $this->ringkasSindromAspek($report->data['sindrom'] ?? []);
        $namaBahasa = $bahasa === 'en' ? 'English' : 'Bahasa Indonesia';

        $systemPrompt = <<<PROMPT
        Kamu adalah asisten penulisan laporan grafologi. Tugasmu merangkai data
        skor & narasi per-aspek berikut (beberapa aspek juga menyertakan bukti
        tulisan tangan spesifik dari Indikator yang tercentang) menjadi SATU
        laporan deskriptif yang mengalir dan komunikatif dalam {$namaBahasa},
        seolah ditulis langsung oleh seorang grafolog profesional untuk
        kliennya. Bukti Indikator boleh dipakai untuk memperkuat/mengkonkretkan
        narasi aspek terkait, bukan didaftar terpisah.

        ATURAN KETAT:
        - JANGAN menambah klaim, contoh, atau interpretasi baru di luar data yang diberikan.
        - JANGAN mengubah makna atau tingkat kepastian pernyataan yang sudah ada.
        - Boleh menyusun ulang urutan, menggabungkan poin yang berhubungan, dan
          menambahkan kalimat transisi/penghubung supaya mengalir sebagai satu
          narasi utuh - bukan daftar per-aspek yang terpisah-pisah.
        - Framing sebagai insight reflektif kepribadian, BUKAN diagnosis klinis
          atau penilaian yang pasti/final.
        - Output HANYA teks laporan (paragraf biasa), tanpa heading markdown,
          tanpa embel-embel/preamble.
        PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => config('services.llm.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post(config('services.llm.endpoint'), [
            'model' => config('services.llm.model'),
            'max_tokens' => 4000,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => $ringkasan,
            ]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal menghasilkan draft narasi terpadu: '.$response->body());
        }

        $teks = trim($response->json('content.0.text') ?? '');
        if ($teks === '') {
            throw new RuntimeException('Respons LLM kosong saat menghasilkan draft narasi terpadu.');
        }

        return $teks;
    }

    private function ensureConfigured(): void
    {
        if (config('services.llm.provider') !== 'api' || ! config('services.llm.api_key')) {
            throw new RuntimeException('LLM belum dikonfigurasi (LLM_PROVIDER/LLM_API_KEY) - narasi terpadu tidak bisa digenerate.');
        }
    }

    /**
     * Sindrom -> Aspek -> Indikator, sama hierarki 3 level yang sudah dipakai
     * di pdf.blade.php ("indikator_terkait" - lihat ScoringController::
     * attachIndikatorNarasi()). Indikator cuma ada kalau sample diskor lewat
     * Measurement Worksheet (mode manual tidak pernah punya
     * sample_indikator_checks) - draft AI untuk laporan mode manual tetap
     * jalan dengan 2 level saja, ini bukan syarat wajib.
     *
     * @param  array<int, array<string, mixed>>  $sindromList
     */
    private function ringkasSindromAspek(array $sindromList): string
    {
        $baris = [];
        foreach ($sindromList as $sindrom) {
            $baris[] = "## {$sindrom['nama']} (rata-rata: {$sindrom['rata_rata_skor']}/10, {$sindrom['band_label_rata_rata']})";
            foreach ($sindrom['aspek'] as $aspek) {
                $baris[] = "- {$aspek['nama']} (skor {$aspek['skor']}/10, {$aspek['band_label']}): {$aspek['narasi']}";
                foreach ($aspek['indikator_terkait'] ?? [] as $indikator) {
                    if (empty($indikator['keterangan'])) {
                        continue;
                    }
                    $baris[] = "  - Bukti tulisan tangan ({$indikator['kode']} {$indikator['nama']}): {$indikator['keterangan']}";
                }
            }
        }

        return implode("\n", $baris);
    }
}
