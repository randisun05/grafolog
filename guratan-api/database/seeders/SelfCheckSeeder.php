<?php

namespace Database\Seeders;

use App\Models\Indikator;
use App\Models\SelfCheckOption;
use App\Models\SelfCheckQuestion;
use Illuminate\Database\Seeder;

/**
 * Pertanyaan awal cek tulisan terpandu (Fase E, 2026-10-17). Setiap opsi menunjuk Indikator KB
 * yang SUDAH ADA lewat (nama indikator, kode aspek) - seeder tidak menulis interpretasi apa pun.
 * Bila KB belum di-seed (mis. dipanggil dari migrasi sebelum GrafologiKnowledgeSeeder), opsi
 * dibuat tanpa indikator dan dilengkapi saat seeder ini dipanggil lagi dari DatabaseSeeder.
 * Idempoten; tidak menimpa pemetaan yang sudah diubah admin.
 */
class SelfCheckSeeder extends Seeder
{
    /** [pertanyaan, petunjuk, [[label, ilustrasi, [nama indikator, kode aspek] | null], ...]] */
    public const QUESTIONS = [
        ['Ke mana arah kemiringan tulisan Anda?', 'Tulis satu kalimat di kertas bergaris, lalu lihat huruf-huruf kecil seperti a, e, o, n.', [
            ['Condong ke kanan', 'slant-right', ['Middle zone upslant very right', '03']],
            ['Tegak lurus', 'slant-vertical', ['Slant in middle and upper zone vertical', '15']],
            ['Condong ke kiri', 'slant-left', ['Middle zone upslant left', '32']],
            ['Berubah-ubah', 'slant-mixed', ['Middle zone upslant irregular', '27']],
        ]],
        ['Seberapa kuat tekanan pena Anda?', 'Raba bagian belakang kertas setelah menulis: apakah bekasnya terasa timbul?', [
            ['Kuat - bekasnya terasa jelas', 'pressure-heavy', ['Pressure heavy', '08']],
            ['Sedang', 'pressure-medium', ['Pressure medium', '07']],
            ['Ringan - hampir tidak berbekas', 'pressure-light', ['Pressure light', '09']],
        ]],
        ['Seberapa besar huruf kecil Anda (a, e, o)?', 'Bandingkan dengan tinggi baris kertas bergaris biasa (sekitar 8 mm).', [
            ['Besar - hampir memenuhi baris', 'size-large', ['Middle zone height large', '02']],
            ['Sedang', 'size-medium', ['Middle zone height copybook', '11']],
            ['Sangat kecil', 'size-small', ['Middle zone height very small', '31']],
            ['Tidak konsisten', 'size-mixed', ['Middle zone height irregular', '26']],
        ]],
        ['Bagaimana jarak antarkata dalam tulisan Anda?', 'Bandingkan jarak antarkata dengan lebar huruf "o" Anda.', [
            ['Rapat', 'spacing-narrow', ['Word spacing narrow', '03']],
            ['Seimbang', 'spacing-balanced', ['Word spacing balanced', '18']],
            ['Lebar', 'spacing-broad', ['Word spacing broad', '06']],
            ['Tidak teratur', 'spacing-mixed', ['Word spacing irregular', '27']],
        ]],
        ['Bagaimana margin kiri pada satu halaman penuh tulisan Anda?', 'Lihat halaman tanpa garis margin tercetak.', [
            ['Makin lebar ke bawah', 'margin-widening', ['Margin on left broadening toward bottom of page', '08']],
            ['Makin sempit ke bawah', 'margin-narrowing', ['Margin on left narrowing toward bottom of page', '33']],
            ['Lurus dan rapi di semua sisi', 'margin-even', ['Margins all four balanced', '25']],
            ['Tidak beraturan', 'margin-irregular', ['Margin on left irregular', '38']],
        ]],
        ['Bagaimana kecepatan menulis Anda sehari-hari?', null, [
            ['Cepat', 'speed-fast', ['Speed fast', '07']],
            ['Lambat dan hati-hati', 'speed-slow', ['Speed slow', '34']],
        ]],
        ['Dibanding tulisan biasa, ukuran tanda tangan Anda...', null, [
            ['Lebih besar', 'sig-larger', ['Signature larger than writing in text', '12']],
            ['Sebanding', 'sig-same', ['Signature compatible with text', '11']],
            ['Lebih kecil', 'sig-smaller', ['Signature smaller than text', '31']],
        ]],
        ['Seperti apa tanda tangan Anda?', null, [
            ['Nama terbaca jelas', 'sig-legible', ['Signature legible', '11']],
            ['Sulit dibaca', 'sig-illegible', ['Signature illegible', '33']],
            ['Diberi garis bawah', 'sig-underlined', ['Signature underlined', '11']],
        ]],
    ];

    public function run(): void
    {
        foreach (self::QUESTIONS as $qi => [$pertanyaan, $petunjuk, $options]) {
            $question = SelfCheckQuestion::firstOrCreate(
                ['pertanyaan' => $pertanyaan],
                ['petunjuk' => $petunjuk, 'sort' => $qi + 1],
            );

            foreach ($options as $oi => [$label, $ilustrasi, $ref]) {
                $option = SelfCheckOption::firstOrCreate(
                    ['question_id' => $question->id, 'label' => $label],
                    ['ilustrasi' => $ilustrasi, 'sort' => $oi + 1],
                );
                if ($option->indikator_id === null && $ref) {
                    $indikator = Indikator::where('nama', $ref[0])
                        ->whereHas('aspek', fn ($q) => $q->where('kode', $ref[1]))
                        ->first();
                    if ($indikator) {
                        $option->update(['indikator_id' => $indikator->id]);
                    }
                }
            }
        }
    }
}
