<?php

namespace App\Services\Reporting;

/**
 * "Resume" laporan untuk klien: ringkasan deterministik (tanpa AI, tanpa teks baru) dari isi laporan -
 * rata-rata per sindrom + 5 ciri paling menonjol (skor tertinggi) dengan kalimat pertama narasi KB.
 * Netral: skor tinggi = intensitas ciri, bukan "baik/buruk" (sebagian sindrom berpolaritas negatif).
 */
class ReportResumeService
{
    public function build(array $data, string $bahasa): array
    {
        $sindrom = collect($data['sindrom'] ?? []);
        $aspek = $sindrom->flatMap(fn ($s) => collect($s['aspek'] ?? [])->map(fn ($a) => $a + ['_sindrom' => $s['nama'] ?? '']));

        return [
            'bahasa' => $bahasa,
            'sindrom' => $sindrom->map(fn ($s) => [
                'nama' => $s['nama'] ?? '', 'rata_rata' => round((float) ($s['rata_rata_skor'] ?? 0), 1), 'band' => $s['band_label_rata_rata'] ?? null,
            ])->values()->all(),
            'menonjol' => $aspek->sortByDesc('skor')->take(5)->values()->map(fn ($a) => [
                'nama' => $a['nama'] ?? $a['kode'], 'sindrom' => $a['_sindrom'], 'skor' => $a['skor'] ?? null, 'band' => $a['band_label'] ?? null,
                'ringkas' => $this->firstSentence((string) ($a['narasi'] ?? '')),
            ])->all(),
            'catatan' => $bahasa === 'en'
                ? 'This summary is a reflective insight tool, not a psychological or clinical diagnosis. A higher score means a more prominent trait, not "better".'
                : 'Ringkasan ini adalah alat insight reflektif, bukan diagnosis psikologis atau klinis. Skor lebih tinggi berarti ciri lebih menonjol, bukan "lebih baik".',
        ];
    }

    private function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (preg_match('/^(.{20,260}?[.!?])(\s|$)/u', $text, $m)) {
            return $m[1];
        }

        return mb_strlen($text) > 220 ? mb_substr($text, 0, 217).'...' : $text;
    }
}
