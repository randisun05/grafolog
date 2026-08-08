<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
use App\Models\Sindrom;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator' on its routes. KM-H (2026-08-08) - lihat
 * "Knowledge Management System" di CLAUDE.md. Murni baca (tidak ada
 * store/update/destroy) - peta konsep untuk dijelajah, bukan diedit lewat
 * sini (edit tetap lewat 6 tab CRUD KM-B..F yang sudah ada).
 *
 * 3 endpoint bertingkat, bukan 1 endpoint raksasa yang mengirim 704
 * Indikator + seluruh relasinya sekaligus - selain lambat, itu juga tidak
 * bisa dijelajah manusia sebagai peta. Frontend memuat overview dulu, lalu
 * meminta detail 1 Aspek/Indikator begitu admin benar-benar mengekliknya.
 */
class ConceptMapController extends Controller
{
    /**
     * Ring pertama peta: 8 Sindrom -> 40 Aspek, dengan jumlah Indikator per
     * Aspek supaya node bisa diberi bobot visual tanpa memuat isinya dulu.
     */
    public function overview(): JsonResponse
    {
        $sindrom = Sindrom::with(['aspek' => function ($q) {
            $q->withCount('indikator')->orderBy('kode');
        }])
            ->orderBy('id')
            ->get(['id', 'kode_romawi', 'nama', 'polaritas_inferred']);

        return response()->json($sindrom);
    }

    /**
     * Ring kedua: daftar Indikator 1 Aspek, ringan (tanpa full rule/cross-
     * reference detail - itu baru dimuat kalau 1 Indikator diklik lewat
     * indikator() di bawah). rules_count/cross_ref_count dipakai frontend
     * untuk menandai node mana yang punya relasi (auto-evaluable atau
     * dirujuk Indikator lain) tanpa perlu memuat isinya.
     */
    public function aspek(Aspek $aspek): JsonResponse
    {
        $indikator = $aspek->indikator()
            ->withCount('rules')
            ->orderBy('posisi')->orderBy('varian')
            ->get(['id', 'kode', 'posisi', 'varian', 'nama', 'aspek_id']);

        $crossRefCounts = IndikatorCrossReference::whereIn('indikator_sumber_id', $indikator->pluck('id'))
            ->selectRaw('indikator_sumber_id, count(*) as total')
            ->groupBy('indikator_sumber_id')
            ->pluck('total', 'indikator_sumber_id');

        $indikator->each(function ($ind) use ($crossRefCounts) {
            $ind->cross_ref_count = (int) ($crossRefCounts[$ind->id] ?? 0);
        });

        return response()->json([
            'aspek' => $aspek->only(['id', 'kode', 'nama']),
            'indikator' => $indikator,
        ]);
    }

    /**
     * Detail penuh 1 Indikator untuk panel relasi: aturan operator (KM-E,
     * Indikator<->Measurement Variable) plus referensi silang KELUAR
     * (Indikator ini memicu yang lain, KM-F) DAN MASUK (dirujuk Indikator
     * lain) - dua arah, supaya admin bisa menjelajah ke kedua arah relasi,
     * bukan cuma satu seperti tampilan tab Referensi Silang biasa.
     */
    public function indikator(Indikator $indikator): JsonResponse
    {
        $indikator->load([
            'aspek:id,kode,nama,sindrom_id',
            'aspek.sindrom:id,kode_romawi,nama',
            'rules.variableA:id,kode,nama',
            'rules.variableB:id,kode,nama',
        ]);

        $keluar = $indikator->referensiKeluar()
            ->where('aktif', true)->where('match_status', 'matched')
            ->get(['id', 'mereferensikan_ke_kode']);
        $targetIndikator = Indikator::whereIn('kode', $keluar->pluck('mereferensikan_ke_kode'))
            ->get(['id', 'kode', 'nama', 'aspek_id'])
            ->keyBy('kode');

        $masuk = IndikatorCrossReference::where('mereferensikan_ke_kode', $indikator->kode)
            ->where('aktif', true)->where('match_status', 'matched')
            ->with('indikatorSumber:id,kode,nama,aspek_id')
            ->get();

        return response()->json([
            'indikator' => $indikator,
            'referensi_keluar' => $keluar->map(fn ($ref) => $targetIndikator->get($ref->mereferensikan_ke_kode))->filter()->values(),
            'referensi_masuk' => $masuk->map(fn ($ref) => $ref->indikatorSumber)->filter()->values(),
        ]);
    }
}
