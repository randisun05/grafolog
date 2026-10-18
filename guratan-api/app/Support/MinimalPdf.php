<?php

namespace App\Support;

/** Pembuat PDF satu-halaman minimal (teks) - untuk data demo dan tes. Ukuran halaman bisa diatur (mm). */
class MinimalPdf
{
    public static function make(string $text = 'Contoh scan tulisan tangan (DATA DUMMY)', float $widthMm = 210, float $heightMm = 297): string
    {
        $w = number_format($widthMm / 25.4 * 72, 2, '.', '');
        $h = number_format($heightMm / 25.4 * 72, 2, '.', '');
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $content = "BT /F1 18 Tf 60 {$h} Td 0 -120 Td ({$safe}) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
