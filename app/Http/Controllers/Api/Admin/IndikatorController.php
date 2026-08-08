<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIndikatorRequest;
use App\Http\Requests\Admin\UpdateIndikatorRequest;
use App\Models\AuditLog;
use App\Models\Indikator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-D (2026-08-08). 704 baris
 * - beda dari 3 controller KM-B/C lain, index() ini WAJIB cari/filter +
 * paginasi, tidak realistis mengirim semuanya sekaligus.
 */
class IndikatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Indikator::with(['aspek:id,kode,nama', 'rules.variableA:id,kode,nama', 'rules.variableB:id,kode,nama'])
            ->orderBy('aspek_id')->orderBy('posisi')->orderBy('varian');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%");
            });
        }
        if ($aspekId = $request->integer('aspek_id')) {
            $query->where('aspek_id', $aspekId);
        }

        return response()->json($query->paginate(25)->withQueryString());
    }

    public function store(StoreIndikatorRequest $request): JsonResponse
    {
        $indikator = Indikator::create($request->validated());

        AuditLog::record('buat_indikator', Indikator::class, $indikator->id, $request->user()->id, $request->ip());

        return response()->json($indikator->load(['aspek:id,kode,nama', 'rules']), 201);
    }

    public function update(UpdateIndikatorRequest $request, Indikator $indikator): JsonResponse
    {
        $indikator->update($request->validated());

        AuditLog::record('ubah_indikator', Indikator::class, $indikator->id, $request->user()->id, $request->ip());

        return response()->json($indikator->load(['aspek:id,kode,nama', 'rules.variableA:id,kode,nama', 'rules.variableB:id,kode,nama']));
    }

    /**
     * Tidak butuh guard cascade seperti Sindrom/Aspek::destroy() -
     * indikator_cross_reference.indikator_sumber_id itu nullOnDelete, bukan
     * cascadeOnDelete, jadi menghapus 1 Indikator paling banter mengosongkan
     * referensi silang yang menunjuk ke situ, tidak menghapus baris lain.
     */
    public function destroy(Request $request, Indikator $indikator): JsonResponse
    {
        $id = $indikator->id;
        $indikator->delete();

        AuditLog::record('hapus_indikator', Indikator::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Indikator dihapus.']);
    }
}
