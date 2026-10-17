<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\Indikator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mini game edukatif (Fase E, 2026-10-17): "Kuis Ciri Tulisan" - tebak aspek kepribadian yang
 * dikaitkan pakem grafologi dengan sebuah ciri tulisan. Soal & kunci diambil LANGSUNG dari
 * pasangan Indikator -> Aspek di KB; penjelasan = teks KB. Tidak ada konten karangan.
 */
class GameController extends Controller
{
    public function quiz(Request $request): JsonResponse
    {
        $n = (int) ($request->validate(['n' => ['sometimes', 'integer', 'min:3', 'max:10']])['n'] ?? 5);
        $aspek = Aspek::get(['id', 'nama', 'nama_id']);
        abort_if($aspek->count() < 4, 503, 'Basis pengetahuan belum tersedia.');

        // Hanya indikator dengan penjelasan asli (bukan "See listing ...") dan yang namanya
        // unik di satu aspek - indikator yang sama di banyak aspek akan membuat kunci ambigu.
        $ambiguous = Indikator::select('nama')->groupBy('nama')->havingRaw('COUNT(DISTINCT aspek_id) > 1')->pluck('nama');
        $picked = Indikator::whereNotIn('nama', $ambiguous)
            ->where('keterangan', 'not like', 'See %')
            ->whereNotNull('keterangan')
            ->inRandomOrder()->limit($n)
            ->get(['id', 'aspek_id', 'nama', 'keterangan']);

        return response()->json([
            'questions' => $picked->map(function (Indikator $i) use ($aspek) {
                $options = $aspek->where('id', '!=', $i->aspek_id)->shuffle()->take(3)
                    ->push($aspek->firstWhere('id', $i->aspek_id))->shuffle()->values();

                return [
                    'id' => $i->id,
                    'ciri' => $i->nama,
                    'options' => $options->map(fn ($a) => ['id' => $a->id, 'nama' => $a->nama_id ?: $a->nama, 'nama_en' => $a->nama]),
                    'answer_id' => $i->aspek_id,
                    'penjelasan_en' => $i->keterangan,
                ];
            })->values(),
        ]);
    }
}
