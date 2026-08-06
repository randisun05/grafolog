<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

/**
 * Nilai default = teks yang SUDAH ada hardcode di LandingView.vue sebelum
 * Commerce Fase E - supaya migrasi ke CMS tidak mengubah tampilan apa pun
 * sampai admin benar-benar mengedit lewat panel. Idempotent lewat
 * firstOrCreate: tidak menimpa perubahan admin kalau seeder dijalankan ulang.
 */
class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'landing_eyebrow' => 'Analisis grafologi tepercaya',
            'landing_tagline' => 'Analisis kepribadian berbasis grafologi - laporan komprehensif dari tulisan tangan, disusun oleh grafolog bersertifikat.',
            'landing_cta_label' => 'Mulai Sekarang',
        ];

        foreach ($defaults as $key => $value) {
            ContentBlock::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
