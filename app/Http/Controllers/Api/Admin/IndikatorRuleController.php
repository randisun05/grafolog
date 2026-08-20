<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIndikatorRuleRequest;
use App\Http\Requests\Admin\UpdateIndikatorRuleRequest;
use App\Models\AuditLog;
use App\Models\Indikator;
use App\Models\IndikatorRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-E (2026-08-08) - operator
 * rules yang menghubungkan 1 Indikator ke Measurement Variable. Selalu
 * dibuat di bawah 1 Indikator (nested route) - tidak ada index() terpisah,
 * daftarnya sudah ikut ke-eager-load di IndikatorController::index().
 */
class IndikatorRuleController extends Controller
{
    public function store(StoreIndikatorRuleRequest $request, Indikator $indikator): JsonResponse
    {
        $rule = $indikator->rules()->create($request->validated());

        AuditLog::record('buat_aturan_indikator', IndikatorRule::class, $rule->id, $request->user()->id, $request->ip());

        return response()->json($rule->load(['variableA:id,kode,nama', 'variableB:id,kode,nama', 'dependsOnIndikator:id,kode,nama']), 201);
    }

    public function update(UpdateIndikatorRuleRequest $request, IndikatorRule $indikatorRule): JsonResponse
    {
        $indikatorRule->update($request->validated());

        AuditLog::record('ubah_aturan_indikator', IndikatorRule::class, $indikatorRule->id, $request->user()->id, $request->ip());

        return response()->json($indikatorRule->load(['variableA:id,kode,nama', 'variableB:id,kode,nama', 'dependsOnIndikator:id,kode,nama']));
    }

    public function destroy(Request $request, IndikatorRule $indikatorRule): JsonResponse
    {
        $id = $indikatorRule->id;
        $indikatorRule->delete();

        AuditLog::record('hapus_aturan_indikator', IndikatorRule::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Aturan operator dihapus.']);
    }
}
