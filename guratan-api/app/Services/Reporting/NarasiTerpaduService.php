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
    /**
     * 16000 (bukan 4000 seperti semula) - laporan lengkap 40 aspek + bukti
     * Indikator bisa butuh output setara 20-40 halaman (~13.000-27.000
     * token), 4000 token (~6-8 halaman) memotong draft di tengah kalimat
     * tanpa error. Aman dipanggil non-streaming sekarang karena generate()
     * dijalankan lewat queue job (GenerateNarasiTerpaduJob), bukan lagi
     * langsung di request HTTP grafolog - tidak ada lagi risiko timeout
     * request web untuk generate yang lama.
     */
    private const MAX_TOKENS = 16000;

    public function generateDraft(PersonalityReport $report, string $bahasa): string
    {
        $this->ensureConfigured();

        $ringkasan = $this->ringkasSindromAspek($report->data['sindrom'] ?? []);
        $ringkasan .= $this->ringkasKombinasiDitemukan($report->data['kombinasi_ditemukan'] ?? []);
        $namaBahasa = $bahasa === 'en' ? 'English' : 'Bahasa Indonesia';

        $systemPrompt = <<<PROMPT
        Kamu adalah asisten penulisan laporan grafologi. Tugasmu merangkai data
        skor & narasi per-aspek berikut (beberapa aspek juga menyertakan bukti
        tulisan tangan spesifik dari Indikator yang tercentang, dan mungkin ada
        "Pola Kombinasi" - temuan dari kombinasi beberapa Aspek/Indikator/Sindrom
        sekaligus, bukan cuma 1 aspek) menjadi SATU laporan deskriptif yang
        mengalir dan komunikatif dalam {$namaBahasa}, seolah ditulis langsung
        oleh seorang grafolog profesional untuk kliennya. Bukti Indikator dan
        Pola Kombinasi boleh dipakai untuk memperkuat/mengkonkretkan narasi,
        bukan didaftar terpisah dari aspek yang relevan.

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

        // Timeout dinaikkan dari default Laravel (30s) - generate 16000 token
        // output bisa makan 1-3 menit, dan sekarang berjalan di dalam queue
        // job jadi tidak lagi terikat batas waktu request HTTP web.
        $response = Http::withHeaders([
            'x-api-key' => config('services.llm.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(300)->post(config('services.llm.endpoint'), [
            'model' => config('services.llm.model'),
            'max_tokens' => self::MAX_TOKENS,
            // System prompt-nya tetap sama persis di setiap generate (tidak
            // menyertakan data laporan) - ditandai cache_control supaya
            // Anthropic tidak memproses ulang dari nol tiap call, sedikit
            // lebih cepat & lebih murah. Data laporan (yang genuinely unik
            // tiap laporan) tetap di `messages`, TIDAK di-cache - benar,
            // karena memang tidak akan pernah cache-hit.
            'system' => [[
                'type' => 'text',
                'text' => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
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

    /**
     * Fingerprint data yang dipakai untuk 1 generate - dipakai
     * ReportController::generateNarasiTerpadu() untuk menolak generate
     * ulang yang percuma (skor/bahasa belum berubah sejak generate
     * terakhir) kecuali grafolog eksplisit minta `force`. Cuma dari
     * `data.sindrom` (skor+narasi final) + bahasa - BUKAN dari
     * narasi_terpadu/narasi_status sendiri, supaya tidak ikut berubah kalau
     * grafolog cuma edit teks manual tanpa mengoreksi skor.
     */
    public static function inputHashFor(PersonalityReport $report, string $bahasa): string
    {
        return hash('sha256', json_encode($report->data['sindrom'] ?? []).'|'.$bahasa);
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

    /**
     * @param  array<int, array{id:int, nama:string, teks_interpretasi:string}>  $kombinasiDitemukan
     */
    private function ringkasKombinasiDitemukan(array $kombinasiDitemukan): string
    {
        if ($kombinasiDitemukan === []) {
            return '';
        }

        $baris = ["\n\n## Pola Kombinasi (dari beberapa Aspek/Indikator/Sindrom sekaligus)"];
        foreach ($kombinasiDitemukan as $temuan) {
            $baris[] = "- {$temuan['nama']}: {$temuan['teks_interpretasi']}";
        }

        return implode("\n", $baris);
    }
}
