<?php

namespace Database\Seeders;

use App\Models\GlossaryTerm;
use Illuminate\Database\Seeder;

/**
 * Konten Game "Memory Match Istilah" (fitur konten publik Fase 3,
 * 2026-09-09) - lihat guratan-api/CLAUDE.md. Istilah nyata dari
 * `measurement_variable` grafologi, definisi ditulis ringkas untuk
 * konteks kartu kuis (bukan definisi teknis penuh untuk mesin scoring).
 * `firstOrCreate` keyed on `istilah` - idempoten.
 */
class GlossaryTermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            ['istilah' => 'Middle Zone', 'definisi' => 'Bagian tengah huruf tanpa tangkai naik/turun, misal badan huruf a, c, e, m, n, o.'],
            ['istilah' => 'Upper Zone', 'definisi' => 'Bagian huruf yang menjulang ke atas garis tengah, misal tangkai huruf b, d, h, l, t.'],
            ['istilah' => 'Lower Zone', 'definisi' => 'Bagian huruf yang turun ke bawah baseline, misal ekor huruf g, j, p, q, y.'],
            ['istilah' => 'Baseline', 'definisi' => 'Garis dasar imajiner tempat huruf-huruf "berdiri" saat menulis.'],
            ['istilah' => 'Slant', 'definisi' => 'Kemiringan tulisan tangan terhadap garis vertikal - condong kanan, kiri, atau tegak.'],
            ['istilah' => 'Pressure', 'definisi' => 'Seberapa kuat pena ditekan ke kertas saat menulis.'],
            ['istilah' => 'Letter Spacing', 'definisi' => 'Jarak antar huruf dalam satu kata.'],
            ['istilah' => 'Word Spacing', 'definisi' => 'Jarak antar kata dalam satu kalimat/baris.'],
            ['istilah' => 'Margin', 'definisi' => 'Ruang kosong di tepi kertas yang sengaja ditinggalkan sebelum mulai/berhenti menulis.'],
            ['istilah' => 'Ductus', 'definisi' => 'Lebar/ketebalan garis guratan pena, dipengaruhi jenis alat tulis dan tekanan.'],
        ];

        foreach ($terms as $term) {
            GlossaryTerm::firstOrCreate(['istilah' => $term['istilah']], $term);
        }
    }
}
