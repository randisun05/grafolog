<?php

namespace App\Services\Reporting;

use App\Models\PersonalityReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReportPdfService
{
    /**
     * Render laporan ke PDF dan simpan ke storage. Hasil di-cache lewat
     * kolom pdf_path - dipanggil ulang hanya kalau file belum ada.
     */
    public function generate(PersonalityReport $report): string
    {
        if ($report->pdf_path && Storage::disk('local')->exists($report->pdf_path)) {
            return $report->pdf_path;
        }

        $pdf = Pdf::loadView('reports.pdf', ['report' => $report]);
        $path = "reports/laporan-{$report->id}.pdf";

        Storage::disk('local')->put($path, $pdf->output());
        $report->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * PDF klien - HANYA narasi_terpadu, tidak pernah breakdown Sindrom/Aspek/
     * Indikator (itu tetap lewat generate() di atas, buat grafolog/admin).
     * Cache terpisah (`pdf_path_klien`) karena kontennya beda total, dan
     * di-invalidate eksplisit oleh ReportController setiap narasi_terpadu
     * berubah (lihat generateNarasiTerpadu/updateNarasiTerpadu) supaya klien
     * tidak pernah dapat PDF basi dari sebelum draft direvisi.
     */
    public function generateKlien(PersonalityReport $report): string
    {
        if ($report->pdf_path_klien && Storage::disk('local')->exists($report->pdf_path_klien)) {
            return $report->pdf_path_klien;
        }

        $pdf = Pdf::loadView('reports.pdf-klien', ['report' => $report]);
        $path = "reports/laporan-klien-{$report->id}.pdf";

        Storage::disk('local')->put($path, $pdf->output());
        $report->update(['pdf_path_klien' => $path]);

        return $path;
    }
}
