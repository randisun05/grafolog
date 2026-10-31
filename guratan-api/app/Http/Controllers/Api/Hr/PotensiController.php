<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Services\B2b\PotensiAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Potensi HR (dipindah dari fitur Supervisor branch lama): rata-rata skor + distribusi level narasi per Aspek
 * seluruh kandidat perusahaan, opsional disaring per Topik. Murni baca atas laporan yang SUDAH disetujui QA.
 */
class PotensiController extends Controller
{
    public function index(Request $request, PotensiAggregationService $aggregator): JsonResponse
    {
        $user = $request->user();
        abort_if($user->company_id === null, 422, 'Akun HR Anda belum terikat ke perusahaan.');

        $topikIds = collect($request->input('topik_ids', []))
            ->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all();

        return response()->json($aggregator->aggregate($aggregator->reportsForCompany($user->company_id), $topikIds));
    }
}
