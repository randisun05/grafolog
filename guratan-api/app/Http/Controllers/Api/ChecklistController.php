<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\ToggleIndikatorCheckRequest;
use App\Models\AuditLog;
use App\Models\HandwritingSample;
use App\Services\Scoring\ChecklistEngineService;
use Illuminate\Http\JsonResponse;

/**
 * KM-G: layar checklist Indikator grafolog - menjalankan
 * ChecklistEngineService atas measurement_readings sample ini (auto-centang
 * + cascade referensi silang), dan endpoint toggle untuk koreksi/centang
 * manual. Otorisasi sama seperti ScoringController/MeasurementController -
 * lihat CLAUDE.md. Tidak menyentuh ScoringController::submit sama sekali -
 * frontend mengubah hasil checklist ini jadi payload `skor` yang sama
 * persis dipakai form manual, lalu POST ke endpoint submit yang sudah ada.
 */
class ChecklistController extends Controller
{
    public function __construct(private ChecklistEngineService $engine) {}

    public function index(HandwritingSample $sample): JsonResponse
    {
        $user = request()->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat melihat checklist Indikator.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan checklist Indikator.');
        abort_if($sample->requiresPayment() && ! $sample->isPaid(), 402, 'Sample ini belum dibayar.');

        return response()->json($this->engine->checklistFor($sample));
    }

    /**
     * `status === 'completed'` sengaja TIDAK diblok (2026-08-17) - dibutuhkan
     * alur koreksi laporan via measurement worksheet, lihat catatan yang
     * sama di MeasurementController::store().
     */
    public function toggle(ToggleIndikatorCheckRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat mengubah checklist Indikator.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan checklist Indikator.');
        abort_if($sample->requiresPayment() && ! $sample->isPaid(), 402, 'Sample ini belum dibayar.');

        $result = $this->engine->toggle(
            $sample,
            (int) $request->validated('indikator_id'),
            (bool) $request->validated('checked'),
            $request->validated('also_uncheck_cascaded', []),
            (bool) $request->validated('confirmed', false),
        );

        if ($result['ok']) {
            AuditLog::record('ubah_centang_indikator', HandwritingSample::class, $sample->id, $user->id, $request->ip());
        }

        return response()->json($result);
    }
}
