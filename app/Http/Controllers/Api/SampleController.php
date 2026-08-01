<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sample\StoreSampleRequest;
use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\ReportAspekScore;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SampleController extends Controller
{
    public function __construct(private ScoringEngineService $scoringEngine) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $samples = HandwritingSample::query()
            ->where('user_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->with('reports:id,sample_id,tier,status,generated_at')
            ->latest()
            ->paginate(20);

        return response()->json($samples);
    }

    public function store(StoreSampleRequest $request): JsonResponse
    {
        $user = $request->user();
        $tier = $request->validated('tier');

        $sample = HandwritingSample::create([
            'user_id' => $user->isGrafolog() ? $request->validated('client_user_id') : $user->id,
            'created_by' => $user->id,
            'tier' => $tier,
            'status' => 'pending',
            'image_path' => $tier === 'rapid'
                ? $request->file('image')->store('handwriting-samples', 'public')
                : null,
        ]);

        if ($tier === 'rapid') {
            $this->generatePlaceholderRapidReport($sample);
        }

        return response()->json($sample->fresh('reports'), 201);
    }

    public function show(Request $request, HandwritingSample $sample): JsonResponse
    {
        $this->authorizeAccess($request, $sample);

        return response()->json($sample->load('reports'));
    }

    private function authorizeAccess(Request $request, HandwritingSample $sample): void
    {
        $user = $request->user();
        abort_unless($sample->user_id === $user->id || $sample->created_by === $user->id, 403);
    }

    /**
     * Rapid tier TIDAK menjalankan computer vision (sengaja ditunda pasca-MVP).
     * Skor per aspek diacak semata sebagai placeholder agar alur upload-ke-laporan
     * bisa didemokan; ini bukan analisis tulisan tangan sungguhan.
     */
    private function generatePlaceholderRapidReport(HandwritingSample $sample): void
    {
        DB::transaction(function () use ($sample) {
            $report = PersonalityReport::create([
                'sample_id' => $sample->id,
                'tier' => 'rapid',
                'status' => 'generating',
            ]);

            $skorPerAspek = [];
            foreach (Aspek::pluck('kode') as $kode) {
                $skorPerAspek[$kode] = random_int(1, 10);
            }

            foreach ($skorPerAspek as $kode => $skor) {
                ReportAspekScore::create([
                    'report_id' => $report->id,
                    'aspek_id' => Aspek::findByKode($kode)->id,
                    'skor' => $skor,
                ]);
            }

            $data = $this->scoringEngine->generate($skorPerAspek);

            $report->update([
                'status' => 'completed',
                'data' => $data,
                'generated_at' => now(),
            ]);

            $sample->update(['status' => 'completed']);
        });
    }
}
