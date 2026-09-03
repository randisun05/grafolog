<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvStreamer
{
    /**
     * Streaming CSV download - dipakai semua controller Rekap Admin
     * (lihat guratan-api/CLAUDE.md). $rows harus iterable (idealnya hasil
     * ->cursor() Eloquent, bukan ->get()) supaya memori tetap flat berapa
     * pun jumlah barisnya, tidak dimuat semua ke memori sekaligus.
     *
     * @param  array<int, string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
