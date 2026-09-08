<?php

namespace App\Services\Supervisor;

use App\Models\PersonalityReport;
use App\Models\SupervisorChatConversation;
use App\Models\SupervisorChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Chat interaktif Supervisor (fitur Supervisor Fase 3, 2026-09-08) - lihat
 * CLAUDE.md "Peran Supervisor" untuk konteks penuh. Ini REVERSI KEDUA atas
 * prinsip "LLM tidak live per-user" (setelah NarasiTerpaduService) -
 * pagar biaya/keselamatan yang sama direplikasi: `ensureConfigured()`
 * gagal bersih (bukan silent fallback), header `x-api-key`/
 * `anthropic-version` (BUKAN `Authorization: Bearer`, bug historis jangan
 * diulang), rate limit khusus di atas throttle umum (lihat routes/api.php).
 *
 * SENGAJA SINKRON, beda dari NarasiTerpaduService yang async lewat queue
 * job - konteks per-pesan di sini jauh lebih kecil (ringkasan agregat,
 * bukan breakdown 40 aspek penuh) dan `max_tokens` respons dibatasi kecil,
 * realistis 5-20 detik dalam 1 request HTTP (Http::timeout(60)) - UX chat
 * butuh terasa hidup, polling ala NarasiTerpaduPanel akan terasa rusak
 * untuk percakapan.
 */
class SupervisorChatService
{
    private const MAX_TOKENS = 1024;

    private const HISTORY_WINDOW = 12;

    /**
     * Batas baris ringkasan per-kandidat sebelum degradasi ke agregat saja
     * (company besar) - lihat compactCandidateSummary().
     */
    private const MAX_CANDIDATE_SUMMARY_LINES = 30;

    public function __construct(private readonly PotensiAggregationService $potensiAggregator) {}

    /**
     * Simpan pesan user DULU (kalau LLM gagal ATAU belum dikonfigurasi,
     * pertanyaan tidak hilang, grafolog/supervisor bisa retry tanpa
     * kehilangan histori - pola sama PaymentController::store(), yang
     * juga menyimpan row Payment SEBELUM memanggil DokuService yang bisa
     * melempar RuntimeException karena kredensial belum diisi), baru
     * panggil Anthropic, baru simpan balasan assistant + token_usage.
     */
    public function sendMessage(SupervisorChatConversation $conversation, string $text, User $actor): SupervisorChatMessage
    {
        // Riwayat diambil SEBELUM pesan baru disimpan - supaya pesan
        // giliran ini tidak muncul dua kali di array `messages` yang
        // dikirim ke Anthropic (sekali polos lewat riwayat, sekali lagi
        // dengan blok data via baris terakhir di bawah).
        $history = $this->historyMessages($conversation);

        SupervisorChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $text,
        ]);

        $this->ensureConfigured();

        $systemPrompt = $this->systemPrompt();
        $dataBlock = $this->contextDataBlock($conversation);

        $response = Http::withHeaders([
            'x-api-key' => config('services.llm.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post(config('services.llm.endpoint'), [
            'model' => config('services.llm.model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => [[
                'type' => 'text',
                'text' => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [
                ...$history,
                ['role' => 'user', 'content' => $dataBlock."\n\nPertanyaan Supervisor: ".$text],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mendapat balasan dari asisten chat: '.$response->body());
        }

        $teks = trim($response->json('content.0.text') ?? '');
        if ($teks === '') {
            throw new RuntimeException('Respons asisten chat kosong.');
        }

        $tokenUsage = ($response->json('usage.input_tokens') ?? 0) + ($response->json('usage.output_tokens') ?? 0);

        return SupervisorChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $teks,
            'token_usage' => $tokenUsage > 0 ? $tokenUsage : null,
        ]);
    }

    private function ensureConfigured(): void
    {
        if (config('services.llm.provider') !== 'api' || ! config('services.llm.api_key')) {
            throw new RuntimeException('LLM belum dikonfigurasi (LLM_PROVIDER/LLM_API_KEY) - chat Supervisor tidak bisa dipakai.');
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Kamu adalah asisten data untuk Supervisor perusahaan di aplikasi
        Guratan (analisis grafologi/kepribadian dari tulisan tangan). Kamu
        menjawab pertanyaan Supervisor tentang data kandidat/karyawan
        perusahaan mereka berdasarkan ringkasan agregat & data kandidat yang
        diberikan di setiap pesan.

        ATURAN KETAT:
        - JANGAN menambah klaim, angka, atau interpretasi baru di luar data
          yang diberikan di pesan ini.
        - JANGAN memberi penilaian final, diagnosis, atau simpulan "lulus/
          tidak lulus" - selalu framing sebagai insight reflektif untuk
          membantu keputusan SDM, bukan alat diagnosis klinis atau skor
          kelayakan pasti.
        - Kalau data yang ditanyakan tidak tersedia di ringkasan yang
          diberikan, katakan terus terang bahwa datanya tidak tersedia -
          JANGAN mengarang.
        - Jawaban singkat dan langsung ke inti, bukan esai panjang.
        PROMPT;
    }

    /**
     * Blok data per-request - TIDAK di-cache (genuinely beda tiap company/
     * tiap giliran karena grafolog bisa menambah laporan baru kapan saja).
     * Dihitung ULANG tiap panggilan (murah, PHP murni), bukan snapshot beku.
     */
    private function contextDataBlock(SupervisorChatConversation $conversation): string
    {
        $sampleIds = $conversation->company->sampleIds();
        $reports = PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')
            ->with('sample:id,user_id', 'sample.user:id,name')
            ->get();

        // Dipakai ulang APA ADANYA dari Dashboard Potensi (Fase 2) -
        // jaminan konkret jawaban chat tidak pernah menyimpang dari angka
        // yang ditampilkan Dashboard Potensi, karena satu sumber kebenaran
        // yang sama.
        $agregat = $this->potensiAggregator->aggregate($reports);

        $baris = ['## Ringkasan Agregat Perusahaan'];
        $baris[] = "Jumlah kandidat: {$agregat['candidate_count']}, jumlah laporan selesai: {$agregat['report_count']}.";
        foreach ($agregat['sindrom'] as $sindrom) {
            $aspekRingkas = collect($sindrom['aspek'])
                ->map(fn ($a) => "{$a['nama']} (rata-rata {$a['avg_skor']})")
                ->implode(', ');
            $baris[] = "- {$sindrom['nama']}: {$aspekRingkas}";
        }

        $baris[] = "\n## Ringkasan Per Kandidat";
        array_push($baris, ...$this->compactCandidateSummary($reports));

        return implode("\n", $baris);
    }

    /**
     * Ringkasan ringkas per-kandidat (nama/tier/status/1-2 Sindrom
     * tertinggi) - BUKAN breakdown 40 Aspek/narasi teks penuh, dibatasi
     * MAX_CANDIDATE_SUMMARY_LINES baris, degradasi ke "hanya agregat"
     * untuk company besar supaya konteks tidak membengkak tanpa batas.
     *
     * @param  Collection<int, PersonalityReport>  $reports
     * @return array<int, string>
     */
    private function compactCandidateSummary($reports): array
    {
        if ($reports->count() > self::MAX_CANDIDATE_SUMMARY_LINES) {
            return ['(Terlalu banyak kandidat untuk dirinci satu per satu - gunakan Ringkasan Agregat di atas.)'];
        }

        return $reports->map(function (PersonalityReport $report) {
            $topSindrom = collect($report->data['sindrom'] ?? [])
                ->sortByDesc('rata_rata_skor')
                ->take(2)
                ->pluck('nama')
                ->implode(', ');

            $nama = $report->sample?->user?->name ?? 'Kandidat';

            return "- {$nama} (tier {$report->tier}, status {$report->status}): Sindrom tertinggi - {$topSindrom}.";
        })->all();
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function historyMessages(SupervisorChatConversation $conversation): array
    {
        return $conversation->messages()
            ->latest('id')
            ->take(self::HISTORY_WINDOW)
            ->get()
            ->reverse()
            ->map(fn (SupervisorChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }
}
