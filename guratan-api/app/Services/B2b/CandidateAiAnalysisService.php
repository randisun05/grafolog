<?php

namespace App\Services\B2b;

use App\Models\Aspek;
use App\Models\CandidateAnalysis;
use App\Models\CandidateProfile;
use App\Models\Order;
use App\Models\PersonalityReport;
use App\Models\ServiceDelivery;
use App\Models\User;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\LlmUnavailableException;
use App\Services\Llm\LlmUsageRecorder;
use App\Services\Llm\NarasiCacheService;
use App\Services\Llm\TextGenerator;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Support\Facades\Log;

/**
 * Analisis gabungan kandidat dengan AI (2026-10-18, permintaan pemilik produk): merangkum skor 40 aspek
 * dari laporan grafologi yang SUDAH DISETUJUI + hasil wawancara terhadap posisi yang dilamar.
 *
 * Batas yang dijaga (prinsip produk, bukan opsional):
 * - Data dikirim tanpa identitas (tanpa nama/email/telepon) - hanya skor, teks KB per aspek, skor & catatan
 *   wawancara, posisi, pendidikan/pengalaman.
 * - AI hanya merangkai teks yang diberikan; dilarang menambah klaim, mendiagnosis, atau memutuskan lolos/tidak.
 * - Hasil disimpan per sidik jari data: data sama = tidak memanggil AI lagi (hemat biaya & privasi).
 * - Tanpa provider AI yang mampu (LLM_PROVIDER=none) atau saat AI gagal: ringkasan berbasis aturan
 *   (sumber `aturan`), ditandai jelas sebagai bukan AI.
 */
class CandidateAiAnalysisService
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
    Kamu asisten analis SDM yang merangkum hasil asesmen grafologi dan wawancara seorang kandidat untuk tim rekrutmen.
    Data yang diberikan adalah satu-satunya sumber - jangan menambah fakta, klaim, atau interpretasi di luar data.

    ATURAN KETAT:
    - Grafologi adalah alat refleksi, bukan diagnosis psikologis/klinis. Jangan membuat diagnosis atau label gangguan.
    - Jangan memutuskan atau menyarankan "diterima/ditolak". Bantu tim memahami dan menyiapkan pertanyaan lanjutan.
    - Skor aspek 1-10 (intensitas ciri, bukan nilai baik/buruk - beberapa aspek seperti kerentanan stres atau area masalah justru bermakna sebaliknya). Skor wawancara 1-5.
    - Gunakan kata ganti netral ("kandidat", "ia"), Bahasa Indonesia yang ringkas dan profesional.
    - Bila hasil grafologi dan wawancara tampak berbeda, sebutkan perbedaannya apa adanya, jangan memaksakan kesimpulan.
    - Maksimal sekitar 300 kata. Format teks polos dengan bagian bertanda "##": Ringkasan; Selaras dengan posisi; Perlu digali di wawancara lanjutan; Catatan kehati-hatian.
    PROMPT;

    public function __construct(private LlmProviderInterface $provider) {}

    public function latestReport(CandidateProfile $profile): ?PersonalityReport
    {
        $sampleIds = Order::where('user_id', $profile->user_id)
            ->whereHas('batch', fn ($q) => $q->where('company_id', $profile->company_id))
            ->whereNotNull('sample_id')->pluck('sample_id');

        return PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('review_status', PersonalityReport::REVIEW_APPROVED)
            ->with('aspekScores')->latest('id')->first();
    }

    public function latestInterview(CandidateProfile $profile): ?ServiceDelivery
    {
        $orderIds = Order::where('user_id', $profile->user_id)
            ->whereHas('batch', fn ($q) => $q->where('company_id', $profile->company_id))->pluck('id');

        return ServiceDelivery::whereIn('order_id', $orderIds)->where('status', 'selesai')
            ->whereHas('component', fn ($q) => $q->where('tipe', 'wawancara'))
            ->with('component:id,tipe,report_fields')->latest('report_submitted_at')->first();
    }

    /**
     * Masukan tanpa identitas. Null bila belum ada laporan grafologi yang disetujui.
     *
     * @return array<string, mixed>|null
     */
    public function input(CandidateProfile $profile): ?array
    {
        $report = $this->latestReport($profile);
        if (! $report) {
            return null;
        }
        $interview = $this->latestInterview($profile);
        $position = $profile->position;
        $keyIds = collect($position?->key_aspek_ids ?? [])->map(fn ($id) => (int) $id);

        $aspek = Aspek::with('sindrom:id,nama_id,nama')->get()->keyBy('id');
        $scored = $report->aspekScores->map(fn ($s) => ['aspek' => $aspek[$s->aspek_id] ?? null, 'skor' => (int) $s->skor])
            ->filter(fn ($r) => $r['aspek'])->values();

        // Aspek yang dibahas AI: kunci posisi + 3 tertinggi + 3 terendah (unik), supaya ringkas & terarah.
        $byScore = $scored->sortByDesc('skor')->values();
        $pick = $scored->filter(fn ($r) => $keyIds->contains($r['aspek']->id))
            ->merge($byScore->take(3))->merge($byScore->reverse()->take(3))->unique(fn ($r) => $r['aspek']->id)->values();

        $fields = collect($interview?->component?->report_fields ?? [])->keyBy('key');

        return [
            'posisi' => $position ? ['nama' => $position->title, 'deskripsi' => $position->description] : null,
            'pendidikan' => $profile->education,
            'pengalaman_tahun' => $profile->experience_years,
            'aspek' => $pick->map(fn ($r) => [
                'nama' => $r['aspek']->nama_id ?: $r['aspek']->nama,
                'sindrom' => $r['aspek']->sindrom?->nama_id ?: $r['aspek']->sindrom?->nama,
                'skor' => $r['skor'],
                'kunci_posisi' => $keyIds->contains($r['aspek']->id),
                'teks_kb' => mb_substr($this->teksKb($r['aspek'], $r['skor']), 0, 600),
            ])->all(),
            'wawancara' => $interview ? collect($interview->report ?? [])->map(fn ($v, $k) => [
                'label' => $fields[$k]['label'] ?? $k,
                'jenis' => $fields[$k]['type'] ?? 'teks',
                'nilai' => is_string($v) ? mb_substr($v, 0, 600) : $v,
            ])->values()->all() : null,
        ];
    }

    /** Teks KB per aspek/level apa adanya (ID bila ada, kalau tidak EN) - tanpa memanggil LLM terjemahan. */
    private function teksKb(Aspek $aspek, int $skor): string
    {
        $level = ScoringEngineService::narasiLevelUntukSkor($skor);

        return NarasiCacheService::teksSumber($aspek, $level, 'id') ?: NarasiCacheService::teksSumber($aspek, $level, 'en');
    }

    /** @return array{analysis: CandidateAnalysis, cached: bool}|null null = belum ada laporan disetujui */
    public function analyze(CandidateProfile $profile, User $by): ?array
    {
        $input = $this->input($profile);
        if ($input === null) {
            return null;
        }
        $hash = hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE));
        if ($existing = CandidateAnalysis::where('candidate_profile_id', $profile->id)->where('input_hash', $hash)->first()) {
            app(LlmUsageRecorder::class)->record(LlmUsageRecorder::PURPOSE_ANALISIS, 'cache');

            return ['analysis' => $existing, 'cached' => true];
        }

        [$summary, $source, $model] = $this->summarize($input);

        return ['analysis' => CandidateAnalysis::create([
            'candidate_profile_id' => $profile->id, 'job_position_id' => $profile->job_position_id,
            'input_hash' => $hash, 'summary' => $summary, 'source' => $source, 'model' => $model, 'created_by' => $by->id,
        ]), 'cached' => false];
    }

    /** @return array{0: string, 1: string, 2: ?string} [teks, sumber, model] */
    private function summarize(array $input): array
    {
        if ($this->provider instanceof TextGenerator) {
            try {
                $text = $this->provider->generateText(self::SYSTEM_PROMPT, $this->prompt($input));

                return [$text, 'ai', (string) config('services.gemini.model')];
            } catch (LlmUnavailableException $e) {
                Log::warning('Analisis kandidat: AI tidak tersedia, memakai ringkasan aturan', ['error' => $e->getMessage()]);
            }
        }

        return [$this->ruleBased($input), 'aturan', null];
    }

    private function prompt(array $input): string
    {
        return "Data kandidat (tanpa identitas), format JSON:\n"
            .json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nTulis analisis gabungan sesuai aturan.";
    }

    /** Ringkasan deterministik - hanya menata ulang data, tanpa interpretasi baru. */
    private function ruleBased(array $input): string
    {
        $lines = ['## Ringkasan (otomatis berbasis aturan, bukan AI)'];
        if ($input['posisi']) {
            $lines[] = "Posisi dilamar: {$input['posisi']['nama']}.";
        }
        $key = collect($input['aspek'])->where('kunci_posisi', true);
        $lines[] = '## Aspek kunci posisi';
        $lines[] = $key->isEmpty()
            ? 'Posisi ini belum memiliki aspek kunci.'
            : $key->map(fn ($a) => "- {$a['nama']}: skor {$a['skor']}/10")->implode("\n");
        $others = collect($input['aspek'])->where('kunci_posisi', false);
        if ($others->isNotEmpty()) {
            $lines[] = '## Aspek menonjol lainnya (tertinggi/terendah)';
            $lines[] = $others->map(fn ($a) => "- {$a['nama']}: skor {$a['skor']}/10")->implode("\n");
        }
        if ($input['wawancara']) {
            $lines[] = '## Hasil wawancara';
            $lines[] = collect($input['wawancara'])->map(fn ($w) => "- {$w['label']}: {$w['nilai']}")->implode("\n");
        }
        $lines[] = '## Catatan kehati-hatian';
        $lines[] = 'Skor aspek menunjukkan intensitas ciri, bukan baik/buruk. Gunakan sebagai bahan diskusi bersama wawancara dan penilaian lain.';

        return implode("\n", $lines);
    }
}
