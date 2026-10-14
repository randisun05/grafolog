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
    public function ambil(Aspek $aspek, string $level, string $bahasa = 'id'): string
    {
        $cached = NarasiCache::where('aspek_id', $aspek->id)
            ->where('level', $level)
            ->where('bahasa', $bahasa)
            ->first();

        if ($cached) {
            return $cached->teks_hasil;
        }

        $teksAsli = $aspek->narasi[$level] ?? $aspek->keterangan_umum ?? '';
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
