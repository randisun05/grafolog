<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScoringRuleBandRequest;
use App\Http\Requests\Admin\UpdateScoringRuleBandRequest;
use App\Models\AuditLog;
use App\Models\ScoringRuleBand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-B (2026-08-08). No
 * dependency guard needed on destroy - ScoringRuleBand is looked up
 * dynamically by polaritas/rentang_skor at report-generation time
 * (ScoringRuleBand::labelUntukSkor()), nothing stores a foreign key to a
 * specific band row.
 */
class ScoringRuleBandController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ScoringRuleBand::orderBy('polaritas')->orderBy('id')->get());
    }

    public function store(StoreScoringRuleBandRequest $request): JsonResponse
    {
        $band = ScoringRuleBand::create($request->validated());

        AuditLog::record('buat_band_skor', ScoringRuleBand::class, $band->id, $request->user()->id, $request->ip());

        return response()->json($band, 201);
    }

    public function update(UpdateScoringRuleBandRequest $request, ScoringRuleBand $scoringRuleBand): JsonResponse
    {
        $scoringRuleBand->update($request->validated());

        AuditLog::record('ubah_band_skor', ScoringRuleBand::class, $scoringRuleBand->id, $request->user()->id, $request->ip());

        return response()->json($scoringRuleBand);
    }

    public function destroy(Request $request, ScoringRuleBand $scoringRuleBand): JsonResponse
    {
        $id = $scoringRuleBand->id;
        $scoringRuleBand->delete();

        AuditLog::record('hapus_band_skor', ScoringRuleBand::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Band skor dihapus.']);
    }
}
