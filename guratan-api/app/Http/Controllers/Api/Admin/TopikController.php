<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTopikRequest;
use App\Http\Requests\Admin\UpdateTopikRequest;
use App\Models\AuditLog;
use App\Models\Topik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Manajemen kategori Topik
 * (mis. Karier, Percintaan) - infrastruktur tagging murni, lihat CLAUDE.md
 * "Topik (kategorisasi)". Tidak dipaginasi (kelas kecil, sama dengan
 * Sindrom/KombinasiTemuan).
 */
class TopikController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Topik::withCount(['aspek', 'kombinasiTemuan'])->orderBy('nama')->get());
    }

    public function store(StoreTopikRequest $request): JsonResponse
    {
        $topik = Topik::create($request->validated());

        AuditLog::record('buat_topik', Topik::class, $topik->id, $request->user()->id, $request->ip());

        return response()->json($topik, 201);
    }

    public function update(UpdateTopikRequest $request, Topik $topik): JsonResponse
    {
        $topik->update($request->validated());

        AuditLog::record('ubah_topik', Topik::class, $topik->id, $request->user()->id, $request->ip());

        return response()->json($topik);
    }

    public function destroy(Request $request, Topik $topik): JsonResponse
    {
        $id = $topik->id;
        $topik->delete();

        AuditLog::record('hapus_topik', Topik::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Topik dihapus.']);
    }
}
