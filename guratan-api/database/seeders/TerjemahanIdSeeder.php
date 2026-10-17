<?php

namespace Database\Seeders;

use App\Models\Aspek;
use App\Models\Sindrom;
use Illuminate\Database\Seeder;

/**
 * Isi kolom *_id (Bahasa Indonesia) di sindrom & aspek dari
 * data/terjemahan_id.json. Status file: DRAF terjemahan AI yang wajib
 * ditinjau grafolog - perbaikan dilakukan di JSON itu (atau lewat admin
 * KB lalu disalin balik ke JSON), bukan di seeder ini. Idempoten.
 */
class TerjemahanIdSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/terjemahan_id.json')), true, flags: JSON_THROW_ON_ERROR);
        $orNull = fn (?string $teks) => ($teks === null || trim($teks) === '') ? null : $teks;

        foreach ($data['sindrom'] as $kodeRomawi => $nama) {
            Sindrom::where('kode_romawi', $kodeRomawi)->update(['nama_id' => $nama]);
        }

        foreach ($data['aspek'] as $kode => $t) {
            Aspek::where('kode', $kode)->update([
                'nama_id' => $orNull($t['nama']),
                'keterangan_umum_id' => $orNull($t['keterangan_umum']),
                'narasi_very_high_id' => $orNull($t['very_high']),
                'narasi_high_id' => $orNull($t['high']),
                'narasi_medium_id' => $orNull($t['medium']),
                'narasi_low_id' => $orNull($t['low']),
            ]);
        }

        $this->command?->info(count($data['aspek']).' aspek & '.count($data['sindrom']).' sindrom diberi terjemahan Bahasa Indonesia (draf).');
    }
}
