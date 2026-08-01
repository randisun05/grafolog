<?php

namespace Tests\Concerns;

use App\Models\Aspek;
use App\Models\ScoringRuleBand;
use App\Models\Sindrom;

trait SeedsGrafologiKb
{
    /**
     * Seed 1 sindrom (HIJAU) + N aspek + band HIJAU lengkap, cukup untuk
     * ScoringEngineService::generate() jalan tanpa exception. Kode aspek
     * dibuat '01'..'0N' berurutan.
     */
    protected function seedMinimalAspek(int $count = 3): Sindrom
    {
        $sindrom = Sindrom::create([
            'kode_romawi' => 'I',
            'nama' => 'Driving Forces',
            'polaritas_inferred' => 'HIJAU',
        ]);

        if (ScoringRuleBand::count() === 0) {
            foreach ([
                'Nilai Rendah' => '1-3',
                'Nilai Sedang' => '4-6',
                'Nilai Tinggi' => '7-10',
            ] as $label => $rentang) {
                ScoringRuleBand::create([
                    'polaritas' => 'HIJAU',
                    'label' => $label,
                    'rentang_skor' => $rentang,
                ]);
            }
        }

        // Offset by existing aspek count so repeated calls within one test
        // (e.g. building fixtures for two different users) don't collide on
        // the unique 'kode' column.
        $offset = Aspek::count();
        for ($i = 1; $i <= $count; $i++) {
            Aspek::create([
                'kode' => str_pad((string) ($offset + $i), 2, '0', STR_PAD_LEFT),
                'sindrom_id' => $sindrom->id,
                'nama' => "Aspek $i",
                'keterangan_umum' => 'umum',
                'narasi_very_high' => "narasi very high $i",
                'narasi_high' => "narasi high $i",
                'narasi_medium' => "narasi medium $i",
                'narasi_low' => "narasi low $i",
            ]);
        }

        return $sindrom;
    }
}
