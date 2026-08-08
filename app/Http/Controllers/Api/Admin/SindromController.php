<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSindromRequest;
use App\Http\Requests\Admin\UpdateSindromRequest;
use App\Models\AuditLog;
use App\Models\Sindrom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-B (2026-08-08) - lihat
 * "Knowledge Management System" di CLAUDE.md untuk konteks penuh.
 */
class SindromController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Sindrom::withCount('aspek')->orderBy('id')->get());
    }

    public function store(StoreSindromRequest $request): JsonResponse
    {
        $sindrom = Sindrom::create($request->validated());

        AuditLog::record('buat_sindrom', Sindrom::class, $sindrom->id, $request->user()->id, $request->ip());

        return response()->json($sindrom, 201);
    }

    public function update(UpdateSindromRequest $request, Sindrom $sindrom): JsonResponse
    {
        $sindrom->update($request->validated());

        AuditLog::record('ubah_sindrom', Sindrom::class, $sindrom->id, $request->user()->id, $request->ip());

        return response()->json($sindrom);
    }

    /**
     * `aspek.sindrom_id` adalah cascadeOnDelete - tanpa guard ini, hapus 1
     * Sindrom diam-diam menghapus semua Aspek (dan Indikator-nya, cascade
     * lagi) di bawahnya. Wajib kosongkan/pindahkan Aspek dulu.
     */
    public function destroy(Request $request, Sindrom $sindrom): JsonResponse
    {
        abort_if($sindrom->aspek()->exists(), 422, 'Tidak bisa menghapus Sindrom yang masih punya Aspek terkait.');

        $id = $sindrom->id;
        $sindrom->delete();

        AuditLog::record('hapus_sindrom', Sindrom::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Sindrom dihapus.']);
    }
}
