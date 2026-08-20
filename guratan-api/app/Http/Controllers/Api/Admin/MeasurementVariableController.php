<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMeasurementVariableRequest;
use App\Http\Requests\Admin\UpdateMeasurementVariableRequest;
use App\Models\AuditLog;
use App\Models\MeasurementVariable;
use App\Models\MetodologiPenilaian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-B (2026-08-08).
 */
class MeasurementVariableController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            MeasurementVariable::with(['kategori', 'metodologi'])->orderBy('id')->get()
        );
    }

    public function store(StoreMeasurementVariableRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['metodologi_id'] ??= MetodologiPenilaian::findByKode('master')?->id;

        $variable = MeasurementVariable::create($data);

        AuditLog::record('buat_variabel_ukur', MeasurementVariable::class, $variable->id, $request->user()->id, $request->ip());

        return response()->json($variable->load(['kategori', 'metodologi']), 201);
    }

    public function update(UpdateMeasurementVariableRequest $request, MeasurementVariable $measurementVariable): JsonResponse
    {
        $measurementVariable->update($request->validated());

        AuditLog::record('ubah_variabel_ukur', MeasurementVariable::class, $measurementVariable->id, $request->user()->id, $request->ip());

        return response()->json($measurementVariable->load(['kategori', 'metodologi']));
    }

    /**
     * `measurement_category.variable_id` adalah cascadeOnDelete - ini
     * disengaja (kategori memang sepenuhnya milik 1 variabel, lihat catatan
     * §3.0 rencana KM), jadi tidak butuh guard seperti Sindrom::destroy().
     * Tapi `indikator_rules.variable_a_id`/`variable_b_id` BEDA (KM-E,
     * 2026-08-08) - itu pekerjaan admin merangkai aturan operator, bukan
     * data anak yang sepenuhnya milik variabel ini; menghapus variabel yang
     * masih dipakai 1+ aturan operator diblokir, sama pola dengan
     * Sindrom/Aspek::destroy().
     */
    public function destroy(Request $request, MeasurementVariable $measurementVariable): JsonResponse
    {
        abort_if(
            $measurementVariable->indikatorRulesAsA()->exists() || $measurementVariable->indikatorRulesAsB()->exists(),
            422,
            'Tidak bisa menghapus variabel yang masih dipakai aturan operator.'
        );

        $id = $measurementVariable->id;
        $measurementVariable->delete();

        AuditLog::record('hapus_variabel_ukur', MeasurementVariable::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Variabel ukur dihapus.']);
    }
}
