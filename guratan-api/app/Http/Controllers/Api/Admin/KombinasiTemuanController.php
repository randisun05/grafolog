<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKombinasiTemuanRequest;
use App\Http\Requests\Admin\UpdateKombinasiTemuanRequest;
use App\Models\AuditLog;
use App\Models\KombinasiTemuan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Manajemen "Kombinasi
 * Temuan" - lihat CLAUDE.md. Tidak dipaginasi (beda dari IndikatorController)
 * karena jumlahnya diperkirakan jauh lebih kecil dari 704 Indikator, sama
 * kelasnya dengan Sindrom (8)/Aspek (40), bukan yang butuh search+paginasi.
 */
class KombinasiTemuanController extends Controller
{
    private const WITH = ['syarat.indikator:id,kode,nama', 'syarat.aspek:id,kode,nama', 'syarat.sindrom:id,kode_romawi,nama'];

    public function index(): JsonResponse
    {
        return response()->json(KombinasiTemuan::with(self::WITH)->orderBy('nama')->get());
    }

    public function store(StoreKombinasiTemuanRequest $request): JsonResponse
    {
        $temuan = KombinasiTemuan::create($request->validated());

        AuditLog::record('buat_kombinasi_temuan', KombinasiTemuan::class, $temuan->id, $request->user()->id, $request->ip());

        return response()->json($temuan->load(self::WITH), 201);
    }

    public function update(UpdateKombinasiTemuanRequest $request, KombinasiTemuan $kombinasiTemuan): JsonResponse
    {
        $kombinasiTemuan->update($request->validated());

        AuditLog::record('ubah_kombinasi_temuan', KombinasiTemuan::class, $kombinasiTemuan->id, $request->user()->id, $request->ip());

        return response()->json($kombinasiTemuan->load(self::WITH));
    }

    public function destroy(Request $request, KombinasiTemuan $kombinasiTemuan): JsonResponse
    {
        $id = $kombinasiTemuan->id;
        $kombinasiTemuan->delete();

        AuditLog::record('hapus_kombinasi_temuan', KombinasiTemuan::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Kombinasi temuan dihapus.']);
    }
}
