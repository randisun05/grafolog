<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAspekRequest;
use App\Http\Requests\Admin\UpdateAspekRequest;
use App\Models\Aspek;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-C (2026-08-08).
 */
class AspekController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Aspek::with('sindrom:id,kode_romawi,nama')->withCount('indikator')->orderBy('kode')->get()
        );
    }

    public function store(StoreAspekRequest $request): JsonResponse
    {
        $aspek = Aspek::create($request->validated());

        AuditLog::record('buat_aspek', Aspek::class, $aspek->id, $request->user()->id, $request->ip());

        return response()->json($aspek->load('sindrom:id,kode_romawi,nama'), 201);
    }

    public function update(UpdateAspekRequest $request, Aspek $aspek): JsonResponse
    {
        $aspek->update($request->validated());

        AuditLog::record('ubah_aspek', Aspek::class, $aspek->id, $request->user()->id, $request->ip());

        return response()->json($aspek->load('sindrom:id,kode_romawi,nama'));
    }

    /**
     * `indikator.aspek_id` adalah cascadeOnDelete - tanpa guard ini, hapus 1
     * Aspek diam-diam menghapus semua Indikator (~10-18 baris) di bawahnya.
     * Wajib kosongkan/pindahkan Indikator dulu, sama pola dengan
     * Sindrom::destroy().
     */
    public function destroy(Request $request, Aspek $aspek): JsonResponse
    {
        abort_if($aspek->indikator()->exists(), 422, 'Tidak bisa menghapus Aspek yang masih punya Indikator terkait.');

        $id = $aspek->id;
        $aspek->delete();

        AuditLog::record('hapus_aspek', Aspek::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Aspek dihapus.']);
    }
}
