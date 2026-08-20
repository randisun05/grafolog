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
}
