<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\IndikatorRule;
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
     * Ring kedua: daftar Indikator 1 Aspek, ringan (tanpa full rule/relasi
     * detail - itu baru dimuat kalau 1 Indikator diklik lewat indikator()
     * di bawah). rules_count/cross_ref_count dipakai frontend untuk
     * menandai node mana yang punya relasi (auto-evaluable atau memicu
     * Indikator lain) tanpa perlu memuat isinya. cross_ref_count sekarang
     * dihitung dari indikator_rules rule_type=indikator_checked
     * (dependentRules) - unifikasi cross-reference 2026-08-19, lihat
     * ChecklistEngineService.
     */
    public function aspek(Aspek $aspek): JsonResponse
    {
        $indikator = $aspek->indikator()
            ->withCount(['rules', 'dependentRules'])
            ->orderBy('posisi')->orderBy('varian')
            ->get(['id', 'kode', 'posisi', 'varian', 'nama', 'aspek_id']);

        $indikator->each(function ($ind) {
            $ind->cross_ref_count = $ind->dependent_rules_count;
        });

        return response()->json([
            'aspek' => $aspek->only(['id', 'kode', 'nama']),
            'indikator' => $indikator,
        ]);
    }

    /**
     * Detail penuh 1 Indikator untuk panel relasi: aturan operator (KM-E,
     * Indikator<->Measurement Variable) plus relasi KELUAR (Indikator ini
     * memicu yang lain) DAN MASUK (dipicu Indikator lain) - dua arah,
     * supaya admin bisa menjelajah ke kedua arah relasi. Keduanya sekarang
     * baca dari indikator_rules rule_type=indikator_checked, bukan tabel
     * cross-reference terpisah (2026-08-19).
     */
    public function indikator(Indikator $indikator): JsonResponse
    {
        $indikator->load([
            'aspek:id,kode,nama,sindrom_id',
            'aspek.sindrom:id,kode_romawi,nama',
            'rules.variableA:id,kode,nama',
            'rules.variableB:id,kode,nama',
            'rules.dependsOnIndikator:id,kode,nama,aspek_id',
        ]);

        // Keluar: Indikator LAIN yang rule-nya depends_on Indikator ini.
        $keluar = IndikatorRule::where('rule_type', 'indikator_checked')
            ->where('depends_on_indikator_id', $indikator->id)
            ->with('indikator:id,kode,nama,aspek_id')
            ->get()
            ->pluck('indikator')
            ->filter()
            ->values();

        // Masuk: rule Indikator ini SENDIRI yang bertipe indikator_checked
        // (sisi kanan/depends_on-nya adalah sumber yang memicu).
        $masuk = $indikator->rules
            ->where('rule_type', 'indikator_checked')
            ->pluck('dependsOnIndikator')
            ->filter()
            ->values();

        return response()->json([
            'indikator' => $indikator,
            'referensi_keluar' => $keluar,
            'referensi_masuk' => $masuk,
        ]);
    }
}
