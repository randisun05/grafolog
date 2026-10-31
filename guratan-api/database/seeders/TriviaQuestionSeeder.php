<?php

namespace Database\Seeders;

use App\Models\TriviaQuestion;
use Illuminate\Database\Seeder;

/**
 * Konten Game "Trivia Grafologi" (fitur konten publik Fase 3, 2026-09-09)
 * - lihat guratan-api/CLAUDE.md. SEMUA soal fakta netral/struktural
 * (jumlah Sindrom/Aspek/Indikator/variabel ukur milik sistem Guratan
 * sendiri, istilah grafometrik nyata seperti Middle Zone/Baseline/Slant)
 * - TIDAK ADA klaim akurasi ilmiah berlebihan (konsisten root
 * CLAUDE.md prinsip #1 "jangan klaim akurasi ilmiah berlebihan"), murni
 * fakta definisi/struktural, satu soal (#13) justru menegaskan framing
 * "insight reflektif, bukan pengganti psikotes klinis".
 * `firstOrCreate` keyed on `pertanyaan` - idempoten, tidak menimpa edit
 * admin lewat panel saat reseed.
 */
class TriviaQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'pertanyaan' => 'Berapa jumlah Sindrom (kategori besar kepribadian) dalam sistem grafologi yang dipakai Guratan?',
                'pilihan' => ['8', '5', '12', '3'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Guratan memakai 8 Sindrom: Driving Forces, Intellect, Functional Ability, Interpersonal Relations, Productivity, Stress Vulnerability, Defenses, dan Problem Areas.',
            ],
            [
                'pertanyaan' => 'Berapa jumlah Aspek (trait kepribadian spesifik) dalam sistem Guratan?',
                'pilihan' => ['40', '20', '60', '100'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Ada 40 Aspek, tersebar di bawah 8 Sindrom.',
            ],
            [
                'pertanyaan' => 'Apa istilah grafologi untuk bagian tengah huruf tanpa tangkai naik/turun (misal badan huruf a, c, e, m, n, o)?',
                'pilihan' => ['Middle Zone', 'Upper Zone', 'Lower Zone', 'Baseline'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Middle Zone adalah bagian tengah huruf - salah satu variabel ukur paling sering dirujuk dalam analisis grafologi.',
            ],
            [
                'pertanyaan' => 'Apa istilah untuk garis dasar imajiner tempat huruf-huruf "berdiri" saat menulis?',
                'pilihan' => ['Baseline', 'Margin', 'Slant', 'Ductus'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Baseline adalah garis dasar acuan - konsisten atau tidaknya tulisan mengikuti baseline juga jadi salah satu hal yang diamati grafolog.',
            ],
            [
                'pertanyaan' => 'Apa istilah grafologi untuk kemiringan tulisan tangan (condong kanan, kiri, atau tegak)?',
                'pilihan' => ['Slant', 'Pressure', 'Spacing', 'Zone'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Slant mengukur kemiringan tulisan terhadap garis vertikal.',
            ],
            [
                'pertanyaan' => 'Apa istilah untuk seberapa kuat pena/alat tulis ditekan ke kertas saat menulis?',
                'pilihan' => ['Pressure', 'Slant', 'Spacing', 'Ductus'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Pressure (tekanan) adalah salah satu variabel ukur grafometrik yang diamati grafolog bersertifikat.',
            ],
            [
                'pertanyaan' => 'Apa nama disiplin ilmu yang menganalisis kepribadian dari tulisan tangan?',
                'pilihan' => ['Grafologi', 'Grafologi Forensik', 'Kaligrafi', 'Tipografi'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Grafologi berfokus pada insight kepribadian - beda dari Grafologi Forensik yang berfokus pada verifikasi keaslian tanda tangan/tulisan.',
            ],
            [
                'pertanyaan' => 'Apa istilah untuk jarak antar huruf dalam satu kata?',
                'pilihan' => ['Letter Spacing', 'Word Spacing', 'Margin', 'Baseline'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Letter Spacing mengukur jarak antar huruf, beda dari Word Spacing yang mengukur jarak antar kata.',
            ],
            [
                'pertanyaan' => 'Apa istilah untuk jarak antar kata dalam satu kalimat/baris?',
                'pilihan' => ['Word Spacing', 'Letter Spacing', 'Line Spacing', 'Margin'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Word Spacing mengukur jarak antar kata.',
            ],
            [
                'pertanyaan' => 'Bagian huruf yang menjulang ke atas garis tengah (misal tangkai huruf b, d, h, l, t) disebut?',
                'pilihan' => ['Upper Zone', 'Middle Zone', 'Lower Zone', 'Baseline'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Upper Zone adalah zona atas huruf.',
            ],
            [
                'pertanyaan' => 'Bagian huruf yang turun ke bawah baseline (misal ekor huruf g, j, p, q, y) disebut?',
                'pilihan' => ['Lower Zone', 'Upper Zone', 'Middle Zone', 'Baseline'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Lower Zone adalah zona bawah huruf.',
            ],
            [
                'pertanyaan' => 'Berapa jumlah Indikator (ciri fisik tulisan tangan spesifik) yang dipakai sebagai bukti dalam sistem Guratan?',
                'pilihan' => ['704', '350', '1000', '500'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Ada 704 Indikator - ciri fisik spesifik yang jadi bukti tiap Aspek.',
            ],
            [
                'pertanyaan' => 'Grafologi bisa dipakai untuk insight kepribadian reflektif, tapi BUKAN pengganti apa?',
                'pilihan' => ['Psikotes/asesmen psikologi klinis', 'Buku harian', 'Tanda tangan', 'Kaligrafi'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Grafologi tidak punya dasar ilmiah sekuat psikometri standar - selalu diposisikan sebagai insight reflektif, bukan alat diagnosis klinis.',
            ],
            [
                'pertanyaan' => 'Alat apa yang biasa dipakai grafolog bersertifikat untuk mengukur tulisan tangan secara presisi?',
                'pilihan' => ['Kaliper', 'Termometer', 'Stopwatch', 'Neraca'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Kaliper dipakai untuk mengukur dimensi tulisan tangan (tinggi huruf, jarak, dst) secara presisi mm.',
            ],
            [
                'pertanyaan' => 'Berapa jumlah variabel ukur grafometrik (margin, spasi, kemiringan, tekanan, dst) yang dipakai grafolog bersertifikat di Guratan?',
                'pilihan' => ['37', '20', '50', '10'],
                'jawaban_benar_index' => 0,
                'penjelasan' => 'Ada 37 variabel ukur grafometrik dengan kategori rentang presisi (mm/rasio) sebagai acuan pengukuran manual grafolog.',
            ],
        ];

        foreach ($questions as $question) {
            TriviaQuestion::firstOrCreate(['pertanyaan' => $question['pertanyaan']], $question);
        }
    }
}
