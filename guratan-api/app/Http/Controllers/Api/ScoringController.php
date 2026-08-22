<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\CorrectScoresRequest;
use App\Http\Requests\Scoring\PreviewScoresRequest;
use App\Http\Requests\Scoring\SubmitScoresRequest;
use App\Models\Aspek;
use App\Models\AuditLog;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\ReportAspekScore;
use App\Models\TokenCost;
use App\Services\Reporting\ReportRevisionService;
use App\Services\Scoring\KombinasiTemuanService;
use App\Services\Scoring\ScoringEngineService;
use App\Services\TokenWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ScoringController extends Controller
{
    public function __construct(
        private ScoringEngineService $scoringEngine,
        private TokenWalletService $tokenWallet,
        private ReportRevisionService $reportRevisions,
        private KombinasiTemuanService $kombinasiTemuan,
    ) {}

    /**
     * Pratinjau live Assessment Workspace: hitung ulang ScoringEngineService
     * dari skor yang sudah diisi sejauh ini (boleh sebagian, tidak perlu 40
     * aspek lengkap seperti submit()). Tidak menyimpan apa pun ke DB - murni
     * baca (Aspek/ScoringRuleBand/NarasiCache lewat cache-through biasa).
     */
    public function preview(PreviewScoresRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat melihat pratinjau skor.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan form skor manual.');
        abort_if($sample->requiresPayment() && ! $sample->isPaid(), 402, 'Sample ini belum dibayar.');

        $skorPerAspek = collect($request->validated('skor'))
            ->mapWithKeys(fn (array $entry) => [$entry['kode'] => (int) $entry['skor']])
            ->all();

        if ($skorPerAspek === []) {
            return response()->json(['sindrom' => []]);
        }

        return response()->json($this->scoringEngine->generate($skorPerAspek));
    }

    /**
     * Terima submit 40 skor grafolog untuk satu sample (tier comprehensive/master),
     * simpan ke report_aspek_scores, lalu hasilkan laporan via ScoringEngineService.
     */
    public function submit(SubmitScoresRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat mengisi form skor.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan form skor manual.');
        abort_if($sample->status === 'completed', 422, 'Sample ini sudah memiliki laporan selesai, tidak bisa diisi ulang.');
        abort_if($sample->requiresPayment() && ! $sample->isPaid(), 402, 'Sample ini belum dibayar.');

        // Token grafolog (2026-08-07): TokenCost::activeTokensFor() null berarti
        // admin belum mengonfigurasi biaya token tier ini - diperlakukan sebagai
        // 0 (tidak ada gate), bukan error, supaya fitur ini tidak diam-diam
        // memblokir grafolog sebelum admin sengaja mengaktifkannya.
        $tokensRequired = TokenCost::activeTokensFor($sample->tier) ?? 0;
        abort_if($tokensRequired > 0 && $user->token_balance < $tokensRequired, 402, 'Token Anda tidak cukup untuk membuat laporan ini. Silakan beli token terlebih dahulu.');

        $report = DB::transaction(function () use ($request, $sample, $user, $tokensRequired) {
            $report = PersonalityReport::create([
                'sample_id' => $sample->id,
                'tier' => $sample->tier,
                'status' => 'generating',
            ]);

            $skorPerAspek = [];
            $aspekByKode = Aspek::whereIn('kode', collect($request->validated('skor'))->pluck('kode'))
                ->get()->keyBy('kode');

            foreach ($request->validated('skor') as $entry) {
                $skorPerAspek[$entry['kode']] = (int) $entry['skor'];
                ReportAspekScore::create([
                    'report_id' => $report->id,
                    'aspek_id' => $aspekByKode[$entry['kode']]->id,
                    'skor' => $entry['skor'],
                    'catatan_grafolog' => $entry['catatan_grafolog'] ?? null,
                ]);
            }

            $data = $this->attachIndikatorNarasi($this->scoringEngine->generate($skorPerAspek), $sample);
            $data['kombinasi_ditemukan'] = $this->kombinasiTemuan->evaluate($skorPerAspek, $sample);

            $report->update([
                'status' => 'completed',
                'data' => $data,
                'generated_at' => now(),
            ]);

            $sample->update(['status' => 'completed']);
            $sample->assignment?->update(['status' => 'completed']);

            if ($tokensRequired > 0) {
                $this->tokenWallet->debit($user, $tokensRequired, ['report_id' => $report->id]);
            }

            return $report;
        });

        return response()->json($report->fresh('aspekScores'), 201);
    }

    /**
     * Koreksi skor untuk sample yang laporannya SUDAH selesai (kebalikan
     * dari submit(), yang justru menolak sample completed). Dipakai grafolog
     * pemilik sample untuk memperbaiki salah input skor SETELAH laporan
     * jadi - versi laporan sebelum dikoreksi disimpan dulu ke
     * report_revisions (audit trail), baru report_aspek_scores & data
     * laporan ditulis ulang. Token TIDAK ditagih ulang - ini koreksi atas
     * laporan yang sudah dibayar/dipotong tokennya, bukan laporan baru.
     */
    public function correct(CorrectScoresRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat mengoreksi skor.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan form skor manual.');
        abort_if($sample->status !== 'completed', 422, 'Sample ini belum memiliki laporan untuk dikoreksi.');
        abort_if($sample->requiresPayment() && ! $sample->isPaid(), 402, 'Sample ini belum dibayar.');

        $report = $sample->reports()->latest()->firstOrFail();

        $report = DB::transaction(function () use ($request, $report, $user, $sample) {
            $this->reportRevisions->snapshotBeforeChange($report, 'koreksi_skor', $user, $request->input('catatan'));

            $aspekByKode = Aspek::whereIn('kode', collect($request->validated('skor'))->pluck('kode'))
                ->get()->keyBy('kode');

            $report->aspekScores()->delete();

            $skorPerAspek = [];
            foreach ($request->validated('skor') as $entry) {
                $skorPerAspek[$entry['kode']] = (int) $entry['skor'];
                ReportAspekScore::create([
                    'report_id' => $report->id,
                    'aspek_id' => $aspekByKode[$entry['kode']]->id,
                    'skor' => $entry['skor'],
                    'catatan_grafolog' => $entry['catatan_grafolog'] ?? null,
                ]);
            }

            $data = $this->attachIndikatorNarasi($this->scoringEngine->generate($skorPerAspek), $sample);
            $data['kombinasi_ditemukan'] = $this->kombinasiTemuan->evaluate($skorPerAspek, $sample);
            $report->update(['data' => $data, 'generated_at' => now()]);

            // Skor berubah -> narasi_terpadu (kalau sudah pernah dibuat) bisa
            // jadi tidak sinkron lagi dengan data baru. SENGAJA TIDAK
            // auto-generate ulang lewat AI di sini (prinsip "LLM cuma
            // dipanggil lewat aksi eksplisit grafolog", lihat CLAUDE.md) -
            // teks lama tetap dipertahankan apa adanya. Tapi kalau statusnya
            // 'final', turunkan balik ke 'draft' supaya klien TIDAK terus
            // melihat narasi yang sudah tidak merefleksikan skor terkini
            // sebagai final. `narasi_input_hash` tidak perlu disentuh -
            // otomatis tidak cocok lagi dengan hash data baru, jadi generate
            // berikutnya (kapan pun grafolog klik) tidak akan ketahan
            // dedup-guard walau tanpa `force`.
            if ($report->narasi_status === 'final') {
                $report->update(['narasi_status' => 'draft', 'pdf_path_klien' => null]);
            }

            AuditLog::record('koreksi_skor_laporan', PersonalityReport::class, $report->id, $user->id, $request->ip());

            return $report;
        });

        return response()->json($report->fresh('aspekScores'));
    }

    /**
     * Tempel narasi per-Indikator (kolom `keterangan`, sebelumnya tidak
     * pernah dipakai di laporan - hanya narasi 4-level per-Aspek) ke tiap
     * entri Aspek di `$data`, dari Indikator yang tercentang lewat
     * measurement worksheet/checklist untuk sample ini. Post-processing
     * murni - ScoringEngineService::generate() sengaja tidak disentuh.
     * No-op untuk laporan mode manual (tidak pernah ada sample_indikator_checks).
     */
    private function attachIndikatorNarasi(array $data, HandwritingSample $sample): array
    {
        $checks = $sample->indikatorChecks()->where('checked', true)->with('indikator.aspek')->get();
        if ($checks->isEmpty()) {
            return $data;
        }

        $byAspekKode = $checks->groupBy(fn ($c) => $c->indikator->aspek->kode);

        foreach ($data['sindrom'] as &$sindrom) {
            foreach ($sindrom['aspek'] as &$aspek) {
                $group = $byAspekKode->get($aspek['kode']);
                if ($group) {
                    $aspek['indikator_terkait'] = $group->map(fn ($c) => [
                        'kode' => $c->indikator->kode,
                        'nama' => $c->indikator->nama,
                        'keterangan' => $c->indikator->keterangan,
                    ])->values()->all();
                }
            }
            unset($aspek);
        }
        unset($sindrom);

        return $data;
    }
}
