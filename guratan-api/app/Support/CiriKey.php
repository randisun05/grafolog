<?php

namespace App\Support;

/**
 * Kunci "ciri tulisan" dari nama Indikator: huruf kecil, tanda baca/kutip
 * dibuang, spasi dirapatkan. Indikator di aspek berbeda dengan kunci sama
 * (mis. "Pressure heavy" di 08 dan 22) adalah ciri fisik yang SAMA - dipakai
 * sinkronisasi ciri bersama (ChecklistEngineService) dan matriks keterkaitan
 * (KnowledgeMatrixService).
 */
class CiriKey
{
    public static function from(string $nama): string
    {
        $k = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($nama));

        return trim(preg_replace('/\s+/', ' ', $k));
    }

    /**
     * Kelompokkan Indikator ber-kunci sama yang tersebar di >= 2 Aspek.
     *
     * @param  iterable<object{id:int, aspek_id:int, nama:string}>  $indikator
     * @return array<string, int[]> kunci => daftar indikator_id
     */
    public static function groups(iterable $indikator): array
    {
        $byKey = [];
        $aspekByKey = [];
        foreach ($indikator as $ind) {
            $key = self::from($ind->nama);
            if ($key === '') {
                continue;
            }
            $byKey[$key][] = $ind->id;
            $aspekByKey[$key][$ind->aspek_id] = true;
        }

        return array_filter($byKey, fn ($ids, $key) => count($aspekByKey[$key]) >= 2, ARRAY_FILTER_USE_BOTH);
    }
}
