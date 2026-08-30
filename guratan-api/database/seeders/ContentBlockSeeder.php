<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

/**
 * Nilai default = teks yang hardcode di LandingView.vue - supaya migrasi ke
 * CMS tidak mengubah tampilan apa pun sampai admin benar-benar mengedit
 * lewat panel. Idempotent lewat firstOrCreate: tidak menimpa perubahan
 * admin kalau seeder dijalankan ulang.
 *
 * Field LIST_KEYS (lihat ContentBlock::LIST_KEYS) disimpan sebagai string
 * JSON-encoded, bukan array PHP langsung - json_encode di sini supaya
 * bentuknya identik dengan yang akan ditulis AdminContentView.vue.
 */
class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'landing_eyebrow' => 'Analisis grafologi tepercaya',
            'landing_hero_title' => 'Tulisan tangan Anda, dibaca oleh ahli sungguhan.',
            'landing_tagline' => 'Analisis kepribadian berbasis grafologi - laporan komprehensif dari tulisan tangan, disusun oleh grafolog bersertifikat.',
            'landing_cta_label' => 'Mulai Sekarang',
            'landing_hero_trust' => '8 Sindrom · 40 Aspek · 704 Indikator tulisan tangan dianalisis grafolog bersertifikat.',

            'landing_compare_heading' => 'Insight kepribadian, tanpa antre psikotes',
            'landing_compare_subtext' => 'Psikotes konvensional lambat, mahal, dan butuh jadwal psikolog. Guratan menawarkan alternatif yang lebih cepat dan terjangkau - tanpa berpura-pura jadi pengganti diagnosis klinis.',
            'landing_compare_old' => json_encode([
                'Jadwal psikolog, antre berhari-hari',
                'Biaya sesi tatap muka yang tinggi',
                'Laporan generik, kurang personal',
            ]),
            'landing_compare_new' => json_encode([
                'Kirim sampel tulisan tangan kapan saja',
                'Harga transparan sejak awal',
                'Dinilai manual oleh grafolog bersertifikat',
            ]),

            'landing_steps_heading' => 'Cara Kerja',
            'landing_steps_subtext' => 'Empat langkah dari sampel tulisan tangan sampai laporan siap dibaca.',
            'landing_steps' => json_encode([
                ['title' => 'Pilih Tier & Kirim', 'desc' => 'Daftar, pilih Comprehensive atau Master, kirim sampel tulisan tangan Anda.'],
                ['title' => 'Grafolog Mengukur', 'desc' => 'Grafolog bersertifikat menilai tulisan tangan Anda memakai kaliper & pakem grafologi ilmiah.'],
                ['title' => 'Laporan Tersusun', 'desc' => 'Sistem merangkai narasi dari basis pengetahuan grafologi - bukan karangan bebas.'],
                ['title' => 'Unduh & Baca', 'desc' => 'Laporan lengkap tersedia di dashboard Anda, bisa diunduh sebagai PDF.'],
            ]),

            'landing_analysis_heading' => 'Bukan sekadar tebak-tebak kepribadian',
            'landing_analysis_subtext' => 'Setiap laporan ditarik dari basis pengetahuan grafologi profesional yang terstruktur.',

            'landing_pricing_heading' => 'Harga',
            'landing_pricing_subtext' => 'Dua tingkat layanan, keduanya dinilai manual oleh grafolog bersertifikat.',

            'landing_honesty_quote' => 'Insight reflektif untuk mengenal diri - bukan vonis, bukan diagnosis.',
            'landing_honesty_points' => json_encode([
                ['title' => 'Dinilai manusia, bukan AI generatif', 'desc' => 'Skor 40 aspek diisi manual oleh grafolog bersertifikat - bukan hasil tebakan model bahasa.'],
                ['title' => 'Narasi dari basis pengetahuan nyata', 'desc' => 'Setiap kalimat laporan ditarik dari referensi grafologi terstruktur, bukan dikarang bebas.'],
                ['title' => 'Bukan pengganti diagnosis klinis', 'desc' => 'Guratan adalah alat bantu refleksi diri, bukan alat diagnosis psikologis atau medis.'],
            ]),

            'landing_signup_heading' => 'Cara Daftar',

            'landing_cta_band_heading' => 'Penasaran apa kata tulisan tangan Anda?',
            'landing_cta_band_subtext' => 'Hasil disusun grafolog bersertifikat, harga transparan sejak awal.',

            // Dukungan pelanggan - lihat ContentBlock::EDITABLE_KEYS.
            'support_email' => 'support@guratan.id',
            'support_whatsapp' => '',
            'support_hours' => 'Senin-Jumat, 09.00-17.00 WIB',
            'support_note' => 'Ada pertanyaan soal laporan, pembayaran, atau akun Anda? Tim kami siap membantu.',

            // Entitas hukum/biro psikologi - sengaja kosong, lihat catatan
            // di ContentBlock::EDITABLE_KEYS kenapa bukan placeholder.
            'legal_entity_name' => '',
            'legal_contact_email' => '',
        ];

        foreach ($defaults as $key => $value) {
            ContentBlock::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
