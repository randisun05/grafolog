<?php

namespace App\Services\Reporting;

use App\Models\PersonalityReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReportPdfService
{
    /**
     * Render laporan ke PDF dan simpan ke storage. Nama file memuat sidik jari
     * isi (data laporan + konsultasi + review), jadi koreksi skor, edit narasi,
     * atau perubahan jadwal otomatis menghasilkan PDF baru - dulu file lama
     * terus dipakai selama masih ada di disk.
     */
    public function generate(PersonalityReport $report): string
    {
        $report->loadMissing('sample.user', 'sample.consultation', 'sample.assignment.grafolog', 'sample.creator');

        $fingerprint = substr(md5(json_encode([
            $report->data,
            $report->sample?->consultation?->only(['scheduled_at', 'meeting_url', 'notes', 'status']),
            $report->reviewed_at,
        ])), 0, 12);
        $path = "reports/laporan-{$report->id}-{$fingerprint}.pdf";

        if ($report->pdf_path === $path && Storage::disk('local')->exists($path)) {
            return $path;
        }

        $grafolog = $report->sample?->assignment?->grafolog
            ?? ($report->sample?->creator?->isGrafolog() ? $report->sample->creator : null);

        $pdf = Pdf::loadView('reports.pdf', ['report' => $report, 'grafolog' => $grafolog]);
        Storage::disk('local')->put($path, $pdf->output());

        if ($report->pdf_path && $report->pdf_path !== $path) {
            Storage::disk('local')->delete($report->pdf_path);
        }
        $report->update(['pdf_path' => $path]);

        return $path;
    }
}
