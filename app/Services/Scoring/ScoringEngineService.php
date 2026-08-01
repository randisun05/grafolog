<?php

namespace App\Services\Scoring;

use App\Models\Aspek;
use App\Models\ScoringRuleBand;
use App\Services\Llm\NarasiCacheService;
use InvalidArgumentException;

class ScoringEngineService
{
    public function __construct(private NarasiCacheService $narasiCache) {}

    /**
     * @param  array<string,int>  $skorPerAspek  kode Excel aspek ('01' => 7, '02' => 5, ...)
     * @return array{sindrom: array<int, array<string, mixed>>}
     */
    public function generate(array $skorPerAspek): array
    {
        $sindromResults = [];

        foreach ($skorPerAspek as $kode => $skor) {
            $skor = (int) $skor;
            if ($skor < 1 || $skor > 10) {
                throw new InvalidArgumentException("Skor untuk aspek '$kode' harus antara 1-10, diterima: $skor");
            }

            $aspek = Aspek::with('sindrom')->where('kode', $kode)->first();
            if (! $aspek) {
                throw new InvalidArgumentException("Aspek dengan kode '$kode' tidak ditemukan");
            }

            $sindrom = $aspek->sindrom;
            $polaritas = $sindrom->polaritas_inferred;
            $bandLabel = ScoringRuleBand::labelUntukSkor($skor, $polaritas);
            $narasiLevel = self::narasiLevelUntukSkor($skor);
            $narasi = $this->narasiCache->ambil($aspek, $narasiLevel);

            $sindromResults[$sindrom->id]['sindrom'] ??= [
                'id' => $sindrom->id,
                'kode_romawi' => $sindrom->kode_romawi,
                'nama' => $sindrom->nama,
                'polaritas' => $polaritas,
                'catatan_polaritas' => $sindrom->catatan_polaritas,
            ];

            $sindromResults[$sindrom->id]['aspek'][] = [
                'kode' => $aspek->kode,
                'nama' => $aspek->nama,
                'skor' => $skor,
                'band_label' => $bandLabel,
                'narasi_level' => $narasiLevel,
                'narasi' => $narasi,
            ];
        }

        $sindromList = [];
        foreach ($sindromResults as $entry) {
            $skorList = array_column($entry['aspek'], 'skor');
            $rataRata = round(array_sum($skorList) / count($skorList), 2);
            $polaritas = $entry['sindrom']['polaritas'];

            $sindromList[] = $entry['sindrom'] + [
                'rata_rata_skor' => $rataRata,
                'band_label_rata_rata' => ScoringRuleBand::labelUntukSkor((int) round($rataRata), $polaritas),
                'aspek' => $entry['aspek'],
            ];
        }

        return ['sindrom' => $sindromList];
    }

    /**
     * Petakan skor 1-10 ke salah satu dari 4 level narasi yang tersedia
     * di data sumber (very_high/high/medium/low). Pemetaan ini independen
     * dari polaritas sindrom - band HIJAU/MERAH hanya untuk label tampilan
     * (Nilai Rendah/Sedang/Tinggi), sedangkan narasi menjelaskan intensitas
     * trait itu sendiri.
     */
    public static function narasiLevelUntukSkor(int $skor): string
    {
        return match (true) {
            $skor <= 3 => 'low',
            $skor <= 6 => 'medium',
            $skor <= 8 => 'high',
            default => 'very_high',
        };
    }
}
