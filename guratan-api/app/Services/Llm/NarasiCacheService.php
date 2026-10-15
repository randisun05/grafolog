<?php

namespace App\Services\Llm;

use App\Models\Aspek;
use App\Models\NarasiCache;
use Illuminate\Support\Facades\Log;

class NarasiCacheService
{
    public function __construct(private LlmProviderInterface $provider) {}

    /**
     * Ambil narasi final untuk 1 aspek + 1 level. Cek cache dulu;
     * kalau belum ada, panggil LLM sekali lalu simpan permanen.
     */
    /**
     * Sumber Excel hanya mengisi narasi very_high untuk 2 dari 40 aspek
     * (dicek 2026-10-15) - skor 9-10 jatuh ke narasi high, yang ada di band
     * "Nilai Tinggi" yang sama, bukan ke keterangan_umum yang tidak spesifik
     * level. Ini fallback tampilan, bukan isi KB baru: begitu narasi
     * very_high diisi lewat admin KB, teks itu yang dipakai.
     */
    public static function teksSumber(Aspek $aspek, string $level): string
    {
        $narasi = $aspek->narasi;
        $candidates = $level === 'very_high' ? [$narasi['very_high'] ?? null, $narasi['high'] ?? null] : [$narasi[$level] ?? null];

        foreach ([...$candidates, $aspek->keterangan_umum] as $teks) {
            if (is_string($teks) && trim($teks) !== '') {
                return $teks;
            }
        }

        return '';
    }

    public function ambil(Aspek $aspek, string $level, string $bahasa = 'id'): string
    {
        $cached = NarasiCache::where('aspek_id', $aspek->id)
            ->where('level', $level)
            ->where('bahasa', $bahasa)
            ->first();

        if ($cached) {
            return $cached->teks_hasil;
        }

        $teksAsli = self::teksSumber($aspek, $level);
        if (! $teksAsli) {
            return '(narasi tidak tersedia di sumber data)';
        }

        try {
            $hasil = $this->provider->rangkaiSatuAspek($teksAsli, [
                'nama_aspek' => $aspek->nama,
                'level' => $level,
            ]);
        } catch (LlmUnavailableException $e) {
            // Laporan tetap jadi dengan teks asli, tapi TIDAK di-cache supaya
            // dicoba ulang begitu key LLM tersedia lagi.
            Log::warning('NarasiCacheService: LLM tidak tersedia, pakai teks asli tanpa cache', [
                'aspek_id' => $aspek->id, 'level' => $level, 'error' => $e->getMessage(),
            ]);

            return $teksAsli;
        }

        NarasiCache::create([
            'aspek_id' => $aspek->id,
            'level' => $level,
            'bahasa' => $bahasa,
            'teks_asli' => $teksAsli,
            'teks_hasil' => $hasil,
            'llm_provider' => config('services.llm.provider', 'none'),
        ]);

        return $hasil;
    }
}
