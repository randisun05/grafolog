<?php

/*
|--------------------------------------------------------------------------
| Harga per tier (Rupiah, tanpa desimal)
|--------------------------------------------------------------------------
|
| comprehensive: sesuai CLAUDE.md root ("~Rp49rb/laporan"), dijadikan 49000.
| master: BELUM ADA ANGKA RESMI dari bisnis - 149000 di bawah ini adalah
| PLACEHOLDER, bukan harga final. Konfirmasi ke user sebelum go-live.
|
*/

return [
    'tiers' => [
        'comprehensive' => (int) env('PRICE_COMPREHENSIVE', 49000),
        'master' => (int) env('PRICE_MASTER', 149000),
    ],
];
