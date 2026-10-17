<?php

namespace App\Services\Llm;

use App\Models\Aspek;
use App\Models\NarasiCache;
use Illuminate\Support\Facades\Log;

/**
 * Sumber narasi laporan per aspek per level, dalam dua bahasa:
 * - Inggris: langsung dari kolom KB (narasi_*).
 * - Indonesia: kolom KB narasi_*_id (draf terjemahan yang ditinjau), lalu
 *   narasi_cache bahasa='id' (hasil LLM), lalu - hanya bila LLM sungguhan
 *   aktif - panggil LLM sekali dan cache. Tidak pernah mengembalikan teks
 *   Inggris berlabel Indonesia; null berarti terjemahan belum tersedia.
 */
class NarasiCacheService
{
    public const PLACEHOLDER = [
        'id' => '(narasi tidak tersedia di sumber data)',
        'en' => '(narrative not available in source data)',
    ];

    public function __construct(private LlmProviderInterface $provider) {}

    /**
     * Sumber Excel hanya mengisi narasi very_high untuk 2 dari 40 aspek
     * (dicek 2026-10-15) - skor 9-10 jatuh ke narasi high, yang ada di band
     * "Nilai Tinggi" yang sama, bukan ke keterangan_umum yang tidak spesifik
     * level. Ini fallback tampilan, bukan isi KB baru.
     */
    public static function teksSumber(Aspek $aspek, string $level, string $bahasa = 'en'): string
    {
        $narasi = $bahasa === 'id' ? $aspek->narasi_id : $aspek->narasi;
        $umum = $bahasa === 'id' ? $aspek->keterangan_umum_id : $aspek->keterangan_umum;
        $candidates = $level === 'very_high' ? [$narasi['very_high'] ?? null, $narasi['high'] ?? null] : [$narasi[$level] ?? null];

        foreach ([...$candidates, $umum] as $teks) {
            if (is_string($teks) && trim($teks) !== '') {
                return $teks;
            }
        }

        return '';
    }

    public function teksInggris(Aspek $aspek, string $level): string
    {
        return self::teksSumber($aspek, $level, 'en') ?: self::PLACEHOLDER['en'];
    }

    public function teksIndonesia(Aspek $aspek, string $level): ?string
    {
        if ($kb = self::teksSumber($aspek, $level, 'id')) {
            return $kb;
        }

        $cached = NarasiCache::where('aspek_id', $aspek->id)->where('level', $level)->where('bahasa', 'id')->value('teks_hasil');
        if ($cached) {
            return $cached;
        }

        $teksAsli = self::teksSumber($aspek, $level, 'en');
        if (! $teksAsli || $this->provider instanceof NullLlmProvider) {
            return null;
        }

        try {
            $hasil = $this->provider->rangkaiSatuAspek($teksAsli, ['nama_aspek' => $aspek->nama, 'level' => $level]);
        } catch (LlmUnavailableException $e) {
            Log::warning('NarasiCacheService: LLM tidak tersedia, terjemahan dilewati tanpa cache', [
                'aspek_id' => $aspek->id, 'level' => $level, 'error' => $e->getMessage(),
            ]);

            return null;
        }

        NarasiCache::create([
            'aspek_id' => $aspek->id,
            'level' => $level,
            'bahasa' => 'id',
            'teks_asli' => $teksAsli,
            'teks_hasil' => $hasil,
            'llm_provider' => config('services.llm.provider', 'none'),
        ]);

        return $hasil;
    }

    /** Narasi utama laporan: Indonesia bila tersedia, jika tidak Inggris. */
    public function ambil(Aspek $aspek, string $level, string $bahasa = 'id'): string
    {
        return $bahasa === 'en'
            ? $this->teksInggris($aspek, $level)
            : ($this->teksIndonesia($aspek, $level) ?? $this->teksInggris($aspek, $level));
    }
}
