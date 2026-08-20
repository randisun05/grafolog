<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\StoreMeasurementReadingsRequest;
use App\Models\AuditLog;
use App\Models\HandwritingSample;
use Illuminate\Http\JsonResponse;

/**
 * KM-G: measurement worksheet grafolog - hasil ukur fisik (37 variabel)
 * untuk 1 sample, input mentah yang ChecklistController/ChecklistEngineService
 * evaluasi lewat indikator_rules. Otorisasi sama seperti ScoringController -
 * lihat CLAUDE.md.
 */
class MeasurementController extends Controller
{
    public function index(HandwritingSample $sample): JsonResponse
    {
        $user = request()->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat melihat measurement worksheet.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');

        return response()->json($sample->measurementReadings()->get(['variable_id', 'nilai', 'nilai_min', 'nilai_max']));
    }

    /**
     * `status === 'completed'` sengaja TIDAK diblok di sini (beda dari
     * ScoringController::submit) - laporan yang sudah selesai tetap butuh
     * worksheet-nya bisa diedit ulang untuk alur koreksi (ReportCorrectionPanel
     * mode "Measurement Worksheet"). Mengedit hasil ukur di sini TIDAK
     * mengubah laporan aktif - itu tetap versi beku sampai grafolog sengaja
     * memanggil ScoringController::correct() dengan tally terbaru.
     */
    public function store(StoreMeasurementReadingsRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat mengisi measurement worksheet.');
        abort_unless($sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan measurement worksheet.');
        abort_if($sample->requiresPayment() && ! $sample->isPaid(), 402, 'Sample ini belum dibayar.');

        foreach ($request->validated('pengukuran') as $entry) {
            $nilai = $entry['nilai'] ?? null;
            $nilaiMin = $entry['nilai_min'] ?? null;
            $nilaiMax = $entry['nilai_max'] ?? null;

            if ($nilai === null && $nilaiMin === null && $nilaiMax === null) {
                // Grafolog mengosongkan field yang sudah pernah tersimpan -
                // hapus baris lama, bukan diam-diam dibiarkan basi (bug
                // ditemukan lewat review 2026-08-08).
                $sample->measurementReadings()->where('variable_id', $entry['variable_id'])->delete();

                continue;
            }

            $sample->measurementReadings()->updateOrCreate(
                ['variable_id' => $entry['variable_id']],
                ['nilai' => $nilai, 'nilai_min' => $nilaiMin, 'nilai_max' => $nilaiMax],
            );
        }

        AuditLog::record('isi_pengukuran', HandwritingSample::class, $sample->id, $user->id, $request->ip());

        return response()->json($sample->measurementReadings()->get(['variable_id', 'nilai', 'nilai_min', 'nilai_max']));
    }
}
