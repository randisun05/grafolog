<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Katalog awal (2026-10-17). Idempoten per kode: produk yang sudah ada tidak ditimpa (admin
 * mungkin sudah mengubah nama/harga/komponennya), hanya yang belum ada yang dibuat. Harga awal
 * bersifat contoh - admin mengaturnya di Kelola Produk. Template laporan komponen = draf, boleh
 * disesuaikan admin. Dipanggil dari migrasi katalog DAN DatabaseSeeder.
 */
class ProductSeeder extends Seeder
{
    public const TEMPLATES = [
        'grafologi' => [],
        'konsultasi' => [
            ['key' => 'ringkasan', 'label' => 'Ringkasan sesi', 'type' => 'textarea'],
            ['key' => 'temuan_utama', 'label' => 'Temuan utama yang dibahas', 'type' => 'textarea'],
            ['key' => 'rekomendasi', 'label' => 'Rekomendasi untuk klien', 'type' => 'textarea'],
        ],
        'wawancara' => [
            ['key' => 'komunikasi', 'label' => 'Kemampuan komunikasi', 'type' => 'score'],
            ['key' => 'stabilitas_emosi', 'label' => 'Stabilitas emosi', 'type' => 'score'],
            ['key' => 'motivasi', 'label' => 'Motivasi', 'type' => 'score'],
            ['key' => 'integritas', 'label' => 'Integritas', 'type' => 'score'],
            ['key' => 'kerja_sama', 'label' => 'Kerja sama tim', 'type' => 'score'],
            ['key' => 'catatan', 'label' => 'Catatan wawancara', 'type' => 'textarea'],
            ['key' => 'rekomendasi', 'label' => 'Rekomendasi', 'type' => 'choice',
                'options' => ['Direkomendasikan', 'Dipertimbangkan', 'Tidak direkomendasikan']],
        ],
        'konseling' => [
            ['key' => 'keluhan_utama', 'label' => 'Keluhan / topik utama', 'type' => 'textarea'],
            ['key' => 'intervensi_diberikan', 'label' => 'Pendekatan yang diberikan', 'type' => 'textarea'],
            ['key' => 'perkembangan', 'label' => 'Perkembangan klien', 'type' => 'score'],
            ['key' => 'tindak_lanjut', 'label' => 'Tindak lanjut', 'type' => 'textarea'],
        ],
        'intervensi' => [
            ['key' => 'tujuan', 'label' => 'Tujuan program', 'type' => 'textarea'],
            ['key' => 'aktivitas', 'label' => 'Aktivitas yang dijalankan', 'type' => 'textarea'],
            ['key' => 'pencapaian', 'label' => 'Pencapaian tujuan', 'type' => 'score'],
            ['key' => 'rekomendasi', 'label' => 'Rekomendasi lanjutan', 'type' => 'textarea'],
        ],
        'lainnya' => [
            ['key' => 'catatan', 'label' => 'Catatan layanan', 'type' => 'textarea'],
        ],
    ];

    public function run(): void
    {
        $masterPrice = PricingPlan::activePriceFor('master') ?? 149000;

        $catalog = [
            [
                'kode' => 'master', 'nama' => 'Master Graphology', 'kategori' => 'utama', 'target' => 'semua',
                'requires_handwriting' => true, 'is_featured' => true, 'sort' => 1, 'price' => $masterPrice,
                'ringkasan' => 'Analisis tulisan tangan 40 aspek oleh grafolog bersertifikat, plus sesi konsultasi hasil.',
                'deskripsi' => 'Produk utama Guratan. Grafolog mengukur tulisan tangan Anda secara manual (pakem grafometrik) dan menyusun laporan 8 sindrom / 40 aspek kepribadian, lalu membahas hasilnya bersama Anda dalam sesi konsultasi.',
                'components' => [['grafologi', 'Laporan Grafologi 40 Aspek'], ['konsultasi', 'Sesi Konsultasi Hasil (60 menit)']],
            ],
            [
                'kode' => 'wawancara', 'nama' => 'Wawancara Terstruktur', 'kategori' => 'pendukung', 'target' => 'semua',
                'sort' => 10, 'price' => 99000,
                'ringkasan' => 'Wawancara 45 menit untuk melengkapi temuan grafologi dengan perilaku nyata.',
                'deskripsi' => 'Wawancara terstruktur yang menilai komunikasi, stabilitas emosi, motivasi, integritas, dan kerja sama. Hasilnya bisa dibandingkan dengan laporan grafologi.',
                'components' => [['wawancara', 'Wawancara Terstruktur']],
            ],
            [
                'kode' => 'konseling', 'nama' => 'Konseling Individu', 'kategori' => 'pendukung', 'target' => 'b2c',
                'sort' => 20, 'price' => 250000,
                'ringkasan' => 'Sesi konseling untuk menindaklanjuti temuan laporan Anda.',
                'deskripsi' => 'Sesi konseling individu dengan pendamping Guratan untuk membahas tantangan pribadi atau karier, berangkat dari hasil analisis Anda.',
                'components' => [['konseling', 'Sesi Konseling (60 menit)']],
            ],
            [
                'kode' => 'intervensi', 'nama' => 'Program Pengembangan Diri', 'kategori' => 'pendukung', 'target' => 'semua',
                'sort' => 30, 'price' => 600000,
                'ringkasan' => 'Program pendampingan 4 sesi dengan tujuan yang terukur.',
                'deskripsi' => 'Program intervensi/coaching 4 sesi untuk satu tujuan pengembangan yang disepakati, dengan laporan pencapaian di akhir program.',
                'components' => [['intervensi', 'Program Pendampingan 4 Sesi']],
            ],
            [
                'kode' => 'paket-rekrutmen', 'nama' => 'Paket Rekrutmen', 'kategori' => 'utama', 'target' => 'b2b',
                'requires_handwriting' => true, 'sort' => 40, 'price' => 229000,
                'ringkasan' => 'Laporan grafologi + wawancara terstruktur per kandidat, siap dibandingkan.',
                'deskripsi' => 'Untuk HR: setiap kandidat mendapat laporan grafologi 40 aspek dan hasil wawancara terstruktur, sehingga temuan tulisan tangan dan perilaku bisa dianalisa bersama.',
                'components' => [['grafologi', 'Laporan Grafologi 40 Aspek'], ['wawancara', 'Wawancara Terstruktur']],
            ],
        ];

        DB::transaction(function () use ($catalog) {
            foreach ($catalog as $item) {
                if (Product::where('kode', $item['kode'])->exists()) {
                    continue;
                }
                $components = $item['components'];
                $price = $item['price'];
                unset($item['components'], $item['price']);

                $product = Product::create($item);
                foreach ($components as $i => [$tipe, $nama]) {
                    $product->components()->create([
                        'tipe' => $tipe, 'nama' => $nama, 'sort' => $i, 'report_fields' => self::TEMPLATES[$tipe],
                    ]);
                }
                if (PricingPlan::activePriceFor($product->kode) === null) {
                    PricingPlan::create(['tier' => $product->kode, 'price' => $price, 'is_active' => true]);
                }
            }
        });
    }
}
