<?php

namespace App\Http\Controllers\Api\Games;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\Indikator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Game "Tebak Kepribadian dari Tulisan" - lihat guratan-api/CLAUDE.md
 * "Konten publik — Fase 3". Publik, tanpa login. Konten diambil LANGSUNG
 * dari KB grafologi nyata (Indikator.keterangan sebagai ciri tulisan,
 * Aspek sebagai jawaban) - bukan dikarang, konsisten prinsip root
 * CLAUDE.md "LLM/konten tidak pernah mengarang interpretasi psikologi
 * dari nol" (di sini malah tanpa LLM sama sekali).
 *
 * Framing wajib "tebak-tebakan edukatif" - jawaban dijelaskan lewat
 * Aspek::keterangan_umum (deskripsi NETRAL/umum), BUKAN narasi_high/dst
 * (yang framing-nya "interpretasi level skor laporan sungguhan" - tidak
 * cocok dipakai di konteks kuis, bisa terkesan mendiagnosis pemain).
 */
class TebakKepribadianController extends Controller
{
    /**
     * Balas 1 pertanyaan acak - TIDAK menyertakan mana pilihan yang benar,
     * itu cuma diketahui server saat answer() dipanggil.
     */
    public function question(): JsonResponse
    {
        $indikator = Indikator::whereNotNull('keterangan')
            ->where('keterangan', '!=', '')
            ->with('aspek')
            ->inRandomOrder()
            ->first();

        abort_if($indikator === null, 503, 'Konten game belum tersedia.');

        $choices = Aspek::where('id', '!=', $indikator->aspek_id)
            ->inRandomOrder()
            ->limit(3)
            ->get(['id', 'nama'])
            ->push($indikator->aspek)
            ->map(fn (Aspek $a) => $a->only(['id', 'nama']))
            ->shuffle()
            ->values();

        return response()->json([
            'indikator_id' => $indikator->id,
            'keterangan' => $indikator->keterangan,
            'choices' => $choices,
        ]);
    }

    public function answer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'indikator_id' => ['required', 'integer', 'exists:indikator,id'],
            'aspek_id' => ['required', 'integer', 'exists:aspek,id'],
        ]);

        $indikator = Indikator::with('aspek')->findOrFail($data['indikator_id']);
        $correct = $indikator->aspek_id === (int) $data['aspek_id'];

        return response()->json([
            'correct' => $correct,
            'correct_aspek' => $indikator->aspek->only(['id', 'nama']),
            'penjelasan' => $indikator->aspek->keterangan_umum,
        ]);
    }
}
