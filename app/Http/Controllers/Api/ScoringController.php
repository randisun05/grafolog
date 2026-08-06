<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\PreviewScoresRequest;
use App\Http\Requests\Scoring\SubmitScoresRequest;
use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\ReportAspekScore;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ScoringController extends Controller
{
    public function __construct(private ScoringEngineService $scoringEngine) {}

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

        $report = DB::transaction(function () use ($request, $sample) {
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

            $data = $this->scoringEngine->generate($skorPerAspek);

            $report->update([
                'status' => 'completed',
                'data' => $data,
                'generated_at' => now(),
            ]);

            $sample->update(['status' => 'completed']);
            $sample->assignment?->update(['status' => 'completed']);

            return $report;
        });

        return response()->json($report->fresh('aspekScores'), 201);
    }
}
