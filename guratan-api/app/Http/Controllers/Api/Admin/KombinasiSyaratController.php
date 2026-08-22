<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKombinasiSyaratRequest;
use App\Models\AuditLog;
use App\Models\KombinasiSyarat;
use App\Models\KombinasiTemuan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Nested di bawah 1
 * KombinasiTemuan (pola sama dengan IndikatorRuleController di bawah
 * Indikator) - tidak ada index()/update() terpisah, daftar syarat sudah
 * ikut ke-eager-load di KombinasiTemuanController, dan syarat yang salah
 * cukup dihapus+dibuat ulang (bukan diedit in-place, sama seperti Aturan
 * Operator Indikator yang sudah ada).
 */
class KombinasiSyaratController extends Controller
{
    public function store(StoreKombinasiSyaratRequest $request, KombinasiTemuan $kombinasiTemuan): JsonResponse
    {
        $syarat = $kombinasiTemuan->syarat()->create($request->validated());

        AuditLog::record('buat_kombinasi_syarat', KombinasiSyarat::class, $syarat->id, $request->user()->id, $request->ip());

        return response()->json($syarat->load(['indikator:id,kode,nama', 'aspek:id,kode,nama', 'sindrom:id,kode_romawi,nama']), 201);
    }

    public function destroy(Request $request, KombinasiSyarat $kombinasiSyarat): JsonResponse
    {
        $id = $kombinasiSyarat->id;
        $kombinasiSyarat->delete();

        AuditLog::record('hapus_kombinasi_syarat', KombinasiSyarat::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Syarat kombinasi dihapus.']);
    }
}
