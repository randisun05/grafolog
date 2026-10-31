<?php

namespace App\Services\B2b;

use App\Models\HrChatConversation;
use App\Models\HrChatMessage;
use App\Models\PersonalityReport;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\LlmUnavailableException;
use App\Services\Llm\TextGenerator;
use Illuminate\Support\Collection;

/**
 * Asisten data HR (dipindah dari fitur Supervisor branch lama, 2026-10-31): HR bertanya soal ringkasan potensi
 * kandidat perusahaannya. Memakai penyedia AI yang sama dengan Analisis Kandidat (TextGenerator, termasuk pencatatan
 * pemakaian/anggaran), sinkron, riwayat 12 pesan terakhir dikemas ke dalam prompt.
 *
 * Batas privasi (sama dengan CandidateAiAnalysisService): data dikirim TANPA identitas - kandidat hanya berlabel
 * "Kandidat N", tidak ada nama/email/telepon. Hanya laporan yang sudah disetujui QA. AI dilarang menambah klaim,
 * mendiagnosis, atau memutuskan lolos/tidak.
 */
class HrChatService
{
    public const PURPOSE = 'chat_hr';

    private const HISTORY_WINDOW = 12;

    private const MAX_CANDIDATE_LINES = 30;

    public const SYSTEM_PROMPT = <<<'PROMPT'
    Kamu asisten data untuk tim HR di aplikasi Guratan (analisis grafologi/kepribadian dari tulisan tangan). Kamu menjawab
    pertanyaan HR tentang kandidat perusahaan mereka HANYA berdasarkan ringkasan agregat & ringkasan per kandidat (berlabel
    "Kandidat N", tanpa identitas) yang diberikan di setiap pesan.

    ATURAN KETAT:
    - Jangan menambah klaim, angka, atau interpretasi di luar data yang diberikan.
    - Grafologi adalah alat refleksi, bukan diagnosis psikologis/klinis. Jangan memberi penilaian final atau simpulan
      "lulus/tidak lulus"; bantu HR memahami data dan menyiapkan pertanyaan lanjutan.
    - Skor aspek 1-10 adalah intensitas ciri, bukan nilai baik/buruk (beberapa aspek justru bermakna sebaliknya).
    - Bila data yang ditanyakan tidak tersedia, katakan terus terang; jangan mengarang.
    - Jawab singkat dan langsung ke inti, Bahasa Indonesia yang profesional.
    PROMPT;

    public function __construct(
        private readonly PotensiAggregationService $aggregator,
        private readonly LlmProviderInterface $provider,
    ) {}

    public function available(): bool
    {
        return $this->provider instanceof TextGenerator;
    }

    /**
     * Pesan HR disimpan DULU (pertanyaan tidak hilang bila AI gagal), lalu AI dipanggil, lalu balasan disimpan.
     *
     * @throws LlmUnavailableException bila AI tidak tersedia/gagal
     */
    public function sendMessage(HrChatConversation $conversation, string $text): HrChatMessage
    {
        $history = $conversation->messages()->latest('id')->take(self::HISTORY_WINDOW)->get()->reverse()->values();

        HrChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => $text]);

        if (! $this->available()) {
            throw new LlmUnavailableException('Penyedia AI belum dikonfigurasi - asisten chat tidak bisa dipakai.');
        }

        $riwayat = $history->map(fn (HrChatMessage $m) => ($m->role === 'user' ? 'HR' : 'Asisten').': '.$m->content)->implode("\n");
        $prompt = ($riwayat !== '' ? "Riwayat percakapan:\n{$riwayat}\n\n" : '')
            .$this->contextBlock($conversation->company_id)
            ."\n\nPertanyaan HR: ".$text;

        $balasan = trim($this->provider->generateText(self::SYSTEM_PROMPT, $prompt, self::PURPOSE));
        if ($balasan === '') {
            throw new LlmUnavailableException('Respons asisten kosong.');
        }

        return HrChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $balasan]);
    }

    /** Konteks per-giliran, dihitung ulang tiap panggilan (laporan baru bisa disetujui kapan saja). */
    public function contextBlock(int $companyId): string
    {
        $reports = $this->aggregator->reportsForCompany($companyId);
        $agregat = $this->aggregator->aggregate($reports);

        $baris = ['## Ringkasan Agregat Perusahaan'];
        $baris[] = "Jumlah kandidat: {$agregat['candidate_count']}, jumlah laporan disetujui: {$agregat['report_count']}.";
        foreach ($agregat['sindrom'] as $sindrom) {
            $aspek = collect($sindrom['aspek'])->map(fn ($a) => "{$a['nama']} (rata-rata {$a['avg_skor']})")->implode(', ');
            $baris[] = "- {$sindrom['nama']}: {$aspek}";
        }

        $baris[] = "\n## Ringkasan Per Kandidat (tanpa identitas)";
        array_push($baris, ...$this->candidateLines($reports));

        return implode("\n", $baris);
    }

    /**
     * @param  Collection<int, PersonalityReport>  $reports
     * @return array<int, string>
     */
    private function candidateLines(Collection $reports): array
    {
        if ($reports->isEmpty()) {
            return ['(Belum ada laporan yang disetujui.)'];
        }
        if ($reports->count() > self::MAX_CANDIDATE_LINES) {
            return ['(Terlalu banyak kandidat untuk dirinci satu per satu - gunakan Ringkasan Agregat di atas.)'];
        }

        return $reports->values()->map(function (PersonalityReport $report, int $i) {
            $top = collect($report->data['sindrom'] ?? [])->sortByDesc('rata_rata_skor')->take(2)->pluck('nama')->implode(', ');

            return '- Kandidat '.($i + 1).": Sindrom dengan skor rata-rata tertinggi - {$top}.";
        })->all();
    }
}
