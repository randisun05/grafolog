<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Artikel awal (Fase E, 2026-10-17). Isinya hanya hal yang faktual tentang produk ini (cara
 * menyiapkan scan sesuai validasi upload, posisi grafologi sebagai alat refleksi) - bukan klaim
 * ilmiah atau interpretasi kepribadian baru. Admin menambah/mengubah lewat /admin/artikel.
 * Idempoten per slug.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'slug' => 'cara-menyiapkan-scan-tulisan-tangan',
                'judul' => 'Cara Menyiapkan Scan Tulisan Tangan untuk Analisis',
                'kategori' => 'panduan',
                'ringkasan' => 'Grafolog mengukur margin, spasi, dan ukuran huruf dalam milimeter. Skala gambar harus tetap - inilah cara menyiapkannya.',
                'isi' => <<<'TXT'
Grafolog Guratan mengukur tulisan tangan Anda secara manual: lebar margin, jarak antarkata, tinggi huruf, kemiringan, dan puluhan variabel lain. Banyak di antaranya diukur dalam milimeter, jadi skala gambar harus sama dengan ukuran aslinya.

Gunakan kertas A4 polos (tanpa garis bila memungkinkan) dan pena yang biasa Anda pakai. Tulis minimal satu halaman dengan gaya Anda sehari-hari, lalu bubuhkan tanda tangan di bagian bawah. Jangan menulis lebih rapi dari biasanya.

Pindai (scan) halaman tersebut, bukan memotretnya. Kamera ponsel menghasilkan perspektif miring dan skala yang berubah-ubah sehingga ukuran milimeter tidak bisa dipercaya. Pilih resolusi 300 dpi dan simpan sebagai JPG atau PNG.

Unggah halaman utuh tanpa dipotong atau diperbesar. Sistem menerima file hingga 30 MB dengan sisi terpanjang minimal 1920 piksel dan sisi terpendek minimal 1080 piksel. Scan A4 300 dpi berukuran sekitar 2480 x 3508 piksel, jadi jauh di atas batas minimal.

Bila Anda memiliki beberapa halaman, unggah semuanya. Grafolog akan memberi tahu bila hasil scan kurang jelas.
TXT,
            ],
            [
                'slug' => 'grafologi-alat-refleksi-bukan-diagnosis',
                'judul' => 'Grafologi: Alat Refleksi, Bukan Diagnosis',
                'kategori' => 'wawasan',
                'ringkasan' => 'Apa yang bisa dan tidak bisa Anda harapkan dari laporan grafologi Guratan.',
                'isi' => <<<'TXT'
Grafologi membaca kecenderungan kepribadian dari ciri-ciri tulisan tangan. Laporan Guratan disusun dari basis pengetahuan grafologi profesional: 8 sindrom, 40 aspek, dan ratusan indikator ciri tulisan, yang dinilai oleh grafolog bersertifikat.

Grafologi tidak memiliki dasar ilmiah sekuat psikometri standar seperti tes kepribadian Big Five. Karena itu kami memposisikan laporan sebagai bahan refleksi: titik awal untuk mengenali kecenderungan diri dan membicarakannya, bukan vonis tentang siapa Anda.

Laporan grafologi bukan diagnosis psikologis atau klinis. Bila Anda sedang mengalami masalah kesehatan mental, hubungi psikolog atau tenaga kesehatan berlisensi.

Untuk perusahaan, hasil grafologi sebaiknya dipakai bersama wawancara, rekam jejak, dan penilaian kompetensi lain - tidak pernah sebagai satu-satunya dasar keputusan rekrutmen.
TXT,
            ],
        ];

        foreach ($articles as $data) {
            Article::firstOrCreate(['slug' => $data['slug']], $data + ['is_published' => true, 'published_at' => now()]);
        }
    }
}
