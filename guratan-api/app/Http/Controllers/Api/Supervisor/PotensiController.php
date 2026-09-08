<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\PersonalityReport;
use App\Services\Supervisor\PotensiAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Potensi Supervisor (fitur Supervisor Fase 2, 2026-09-08) -
 * agregat rata-rata skor + distribusi narasi_level seluruh kandidat
 * company, opsional disaring per Topik. Murni baca - tidak pernah
 * memanggil ulang mesin skoring/AI, lihat PotensiAggregationService.
 */
class PotensiController extends Controller
{
    public function index(Request $request, PotensiAggregationService $aggregator): JsonResponse
    {
        $user = $request->user();

        abort_if($user->company_id === null, 422, 'Akun Supervisor Anda belum terikat ke perusahaan.');

        $topikIds = collect($request->input('topik_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->all();

        $sampleIds = $user->company->sampleIds();

        $reports = PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')
            ->with('sample:id,user_id')
            ->get();

        return response()->json($aggregator->aggregate($reports, $topikIds));
    }
}
