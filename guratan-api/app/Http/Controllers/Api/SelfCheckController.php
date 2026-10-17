<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\SelfCheckOption;
use App\Models\SelfCheckQuestion;
use App\Models\SelfCheckResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Cek tulisan terpandu - lead magnet pengganti tier Rapid (Fase E, 2026-10-17). Publik.
 * Pengguna menilai ciri tulisannya sendiri; tiap opsi = satu Indikator KB. Hasil = aspek yang
 * paling banyak tersentuh (maks 5) beserta teks KB apa adanya. TIDAK ada skor 1-10 - itu hanya
 * dari pengukuran grafolog - dan tidak ada teks karangan.
 */
class SelfCheckController extends Controller
{
    public const MAX_ASPEK = 5;

    public const DISCLAIMER = 'Hasil ini disusun dari jawaban Anda sendiri tentang ciri tulisan Anda, bukan dari '
        .'pengukuran grafolog. Grafologi adalah alat refleksi, bukan diagnosis psikologis. Untuk analisis 40 aspek '
        .'yang diukur grafolog bersertifikat dari scan tulisan Anda, pesan Master Graphology.';

    public function index(): JsonResponse
    {
        return response()->json(
            SelfCheckQuestion::where('is_active', true)->orderBy('sort')
                ->with('options:id,question_id,label,ilustrasi,sort')
                ->get(['id', 'pertanyaan', 'petunjuk', 'sort'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:3'],
            'answers.*' => ['integer'],
            'email' => ['nullable', 'email', 'max:255'],
            'consent' => ['nullable', 'boolean'],
        ]);
        // Email hanya disimpan dengan persetujuan eksplisit (UU PDP) - dipakai untuk mengirim hasil & promo.
        if (! empty($data['email']) && empty($data['consent'])) {
            return response()->json([
                'message' => 'Centang persetujuan untuk menyimpan email Anda.',
                'errors' => ['consent' => ['Centang persetujuan untuk menyimpan email Anda.']],
            ], 422);
        }

        // answers: {question_id: option_id}; opsi harus milik pertanyaan aktif yang disebut.
        $options = SelfCheckOption::whereIn('id', array_values($data['answers']))
            ->whereHas('question', fn ($q) => $q->where('is_active', true))
            ->get()
            ->filter(fn ($o) => (int) ($data['answers'][$o->question_id] ?? 0) === $o->id);
        abort_if($options->count() < 3, 422, 'Jawab minimal 3 pertanyaan.');

        $user = $request->user('sanctum');
        $result = SelfCheckResult::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'email' => $data['email'] ?? $user?->email,
            'consented_at' => ! empty($data['email']) ? now() : null,
            'answers' => $options->mapWithKeys(fn ($o) => [$o->question_id => $o->id])->all(),
            'aspek_ids' => $this->rankAspek($options),
        ]);
        AuditLog::record('cek_tulisan', SelfCheckResult::class, $result->id, $user?->id, $request->ip());

        return response()->json($this->present($result), 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->present(SelfCheckResult::where('uuid', $uuid)->firstOrFail()));
    }

    /** @return array<int, int> aspek_id terurut: paling banyak indikator terpilih, lalu urutan KB. */
    private function rankAspek($options): array
    {
        return $options->load('indikator:id,aspek_id')
            ->pluck('indikator.aspek_id')->filter()
            ->countBy()
            ->sortKeys()
            ->sortDesc()
            ->keys()
            ->take(self::MAX_ASPEK)
            ->values()
            ->all();
    }

    private function present(SelfCheckResult $result): array
    {
        $options = SelfCheckOption::whereIn('id', array_values($result->answers))
            ->with(['question:id,pertanyaan', 'indikator:id,aspek_id,nama,keterangan'])
            ->get();
        $aspek = Aspek::whereIn('id', $result->aspek_ids)->with('sindrom:id,kode_romawi,nama,nama_id')->get()->keyBy('id');
        $master = Product::where('kode', 'master')->first();

        return [
            'uuid' => $result->uuid,
            'created_at' => $result->created_at,
            'aspek' => collect($result->aspek_ids)->filter(fn ($id) => isset($aspek[$id]))->map(fn ($id) => [
                'id' => $id,
                'nama' => $aspek[$id]->nama_id ?: $aspek[$id]->nama,
                'nama_en' => $aspek[$id]->nama,
                'sindrom' => $aspek[$id]->sindrom?->nama_id ?: $aspek[$id]->sindrom?->nama,
                'keterangan' => $aspek[$id]->keterangan_umum_id ?: null,
                'keterangan_en' => $aspek[$id]->keterangan_umum,
                'ciri' => $options->filter(fn ($o) => $o->indikator?->aspek_id === $id)->map(fn ($o) => [
                    'pertanyaan' => $o->question?->pertanyaan,
                    'jawaban' => $o->label,
                    'indikator' => $o->indikator->nama,
                    'keterangan_en' => $o->indikator->keterangan,
                ])->values(),
            ])->values(),
            'answered' => $options->count(),
            'disclaimer' => self::DISCLAIMER,
            'cta' => $master ? ['kode' => $master->kode, 'nama' => $master->nama, 'price' => $master->currentPrice()] : null,
        ];
    }
}
