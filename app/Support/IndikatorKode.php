<?php

namespace App\Support;

class IndikatorKode
{
    /**
     * Parse posisi (1-10) dan varian huruf opsional (a/b/c/...) dari kode
     * Excel asli, mis. "01-1'" -> posisi 1, varian null; "01-3a" -> posisi 3,
     * varian 'a'; "35-6-dup3" -> posisi 6, varian null (suffix "-dupN"
     * menandai baris duplikat pada posisi/varian yang sama, bukan varian
     * baru); "31 5b" -> posisi 5, varian 'b' (spasi jadi pemisah, bukan '-').
     *
     * @return array{posisi: int|null, varian: string|null}
     */
    public static function parse(string $kode): array
    {
        if (! preg_match("/^\d{1,2}[\s-](\d{1,2})([a-z])?'?(?:-dup\d+)?$/i", $kode, $m)) {
            return ['posisi' => null, 'varian' => null];
        }

        return [
            'posisi' => (int) $m[1],
            'varian' => isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : null,
        ];
    }
}
