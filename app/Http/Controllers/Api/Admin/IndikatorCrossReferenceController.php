<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIndikatorCrossReferenceRequest;
use App\Http\Requests\Admin\UpdateIndikatorCrossReferenceRequest;
use App\Models\AuditLog;
use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-F (2026-08-08) -
 * mengaktifkan `indikator_cross_reference` (257-280 baris, sebelumnya
 * dorman sejak konversi awal) jadi data yang bisa dikelola admin, lihat
 * §3.3 "Rencana Knowledge Management System". Paginasi sama seperti
 * IndikatorController - volumenya sebanding (ratusan baris).
 *
 * CATATAN PENTING: ini BUKAN cascade-trigger UI itu sendiri (checking
 * Indikator A men-suggest Indikator B) - itu baru masuk akal dibangun di
 * KM-G begitu Form Indikator sungguhan (measurement worksheet) ada.
 * Fase ini murni panel kelola data referensi silangnya (aktif/nonaktif,
 * perbaiki target yang salah, tambah/hapus baris).
 */
class IndikatorCrossReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = IndikatorCrossReference::with('indikatorSumber:id,kode,nama')->orderBy('id');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('indikator_sumber_raw', 'like', "%{$search}%")
                    ->orWhere('mereferensikan_ke_kode', 'like', "%{$search}%");
            });
        }
        if ($request->has('aktif')) {
            $query->where('aktif', $request->boolean('aktif'));
        }
        if ($matchStatus = $request->string('match_status')->trim()->value()) {
            $query->where('match_status', $matchStatus);
        }

        return response()->json($query->paginate(25)->withQueryString());
    }

    public function store(StoreIndikatorCrossReferenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['match_status'] = $this->computeMatchStatus($data);

        $row = IndikatorCrossReference::create($data);

        AuditLog::record('buat_referensi_silang', IndikatorCrossReference::class, $row->id, $request->user()->id, $request->ip());

        return response()->json($row->load('indikatorSumber:id,kode,nama'), 201);
    }

    public function update(UpdateIndikatorCrossReferenceRequest $request, IndikatorCrossReference $indikatorCrossReference): JsonResponse
    {
        $data = $request->validated();
        $merged = array_merge($indikatorCrossReference->only(['indikator_sumber_id', 'mereferensikan_ke_kode']), $data);
        $data['match_status'] = $this->computeMatchStatus($merged);

        $indikatorCrossReference->update($data);

        AuditLog::record('ubah_referensi_silang', IndikatorCrossReference::class, $indikatorCrossReference->id, $request->user()->id, $request->ip());

        return response()->json($indikatorCrossReference->load('indikatorSumber:id,kode,nama'));
    }

    public function destroy(Request $request, IndikatorCrossReference $indikatorCrossReference): JsonResponse
    {
        $id = $indikatorCrossReference->id;
        $indikatorCrossReference->delete();

        AuditLog::record('hapus_referensi_silang', IndikatorCrossReference::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Referensi silang dihapus.']);
    }

    /**
     * "Matched" berarti sumber DAN target sama-sama menunjuk ke Indikator
     * yang benar-benar ada - dihitung server, bukan input admin (lihat
     * catatan di StoreIndikatorCrossReferenceRequest).
     */
    private function computeMatchStatus(array $data): string
    {
        $sumberValid = ! empty($data['indikator_sumber_id']);
        $targetValid = ! empty($data['mereferensikan_ke_kode'])
            && Indikator::where('kode', $data['mereferensikan_ke_kode'])->exists();

        return ($sumberValid && $targetValid) ? 'matched' : 'unmatched';
    }
}
