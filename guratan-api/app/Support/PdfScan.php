<?php

namespace App\Support;

/**
 * Pemeriksa ringan PDF hasil scan (2026-10-18), tanpa pustaka PDF: membaca jumlah halaman dan
 * ukuran halaman pertama (/MediaBox, poin 1/72 inci -> mm). Scanner mencatat ukuran fisik kertas
 * di MediaBox, jadi ini cara murah memastikan skala asli (A4/F4) terjaga. Objek terkompresi
 * (ObjStm) ikut dibongkar; bila ukuran tetap tak terbaca hasilnya null (tidak ditolak otomatis).
 */
class PdfScan
{
    private const PT_TO_MM = 25.4 / 72;

    /** Ukuran kertas yang diterima (mm, portrait) + toleransi. */
    public const PAPERS = ['A4' => [210, 297], 'F4' => [215, 330]];

    public const TOLERANCE = 0.04;

    /** @return array{valid: bool, pages: int, width_mm: ?int, height_mm: ?int, paper: ?string, unsafe: bool} */
    public static function inspect(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || ! str_starts_with(ltrim($raw), '%PDF-')) {
            return ['valid' => false, 'pages' => 0, 'width_mm' => null, 'height_mm' => null, 'paper' => null, 'unsafe' => false];
        }

        $haystacks = [$raw];
        if (preg_match_all('#/Type\s*/ObjStm.*?stream\r?\n(.*?)\r?\nendstream#s', $raw, $m)) {
            foreach ($m[1] as $stream) {
                $out = @gzuncompress($stream);
                if ($out !== false) {
                    $haystacks[] = $out;
                }
            }
        }

        $pages = 0;
        $box = null;
        foreach ($haystacks as $h) {
            $pages += preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $h);
            if ($box === null && preg_match('#/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]#', $h, $b)) {
                $box = [abs((float) $b[3] - (float) $b[1]), abs((float) $b[4] - (float) $b[2])];
            }
        }

        $w = $box ? (int) round($box[0] * self::PT_TO_MM) : null;
        $h = $box ? (int) round($box[1] * self::PT_TO_MM) : null;

        $unsafe = false;
        foreach ($haystacks as $hay) {
            if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|RichMedia|XFA|AA)(?![a-zA-Z])#', $hay)) {
                $unsafe = true;
                break;
            }
        }

        return ['valid' => true, 'pages' => $pages, 'width_mm' => $w, 'height_mm' => $h, 'paper' => $w ? self::paper($w, $h) : null, 'unsafe' => $unsafe];
    }

    public static function paper(int $w, int $h): ?string
    {
        [$short, $long] = [min($w, $h), max($w, $h)];
        foreach (self::PAPERS as $name => [$pw, $ph]) {
            if (abs($short - $pw) / $pw <= self::TOLERANCE && abs($long - $ph) / $ph <= self::TOLERANCE) {
                return $name;
            }
        }

        return null;
    }
}
