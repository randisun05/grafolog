<?php

namespace App\Services\Reporting;

use App\Models\PersonalityReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReportPdfService
{
    public function __construct(private ReportLocalizer $localizer) {}

    /**
     * Render laporan ke PDF dalam satu bahasa (id|en) dan simpan ke storage.
     * Nama file memuat bahasa + sidik jari isi (data + konsultasi + review),
     * jadi koreksi skor, edit narasi, perubahan jadwal, atau terjemahan KB
     * yang diperbarui otomatis menghasilkan PDF baru.
     */
    public function generate(PersonalityReport $report, string $bahasa = 'id'): string
    {
        $report->loadMissing('sample.user', 'sample.consultation', 'sample.assignment.grafolog', 'sample.creator');
        $data = $this->localizer->localize($report->data ?? [], $bahasa);

        $fingerprint = substr(md5(json_encode([
            $data,
            $report->sample?->consultation?->only(['scheduled_at', 'meeting_url', 'notes', 'status']),
            $report->reviewed_at,
        ])), 0, 12);
        $prefix = "reports/laporan-{$report->id}-{$bahasa}-";
        $path = $prefix.$fingerprint.'.pdf';

        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return $path;
        }

        $grafolog = $report->sample?->assignment?->grafolog
            ?? ($report->sample?->creator?->isGrafolog() ? $report->sample->creator : null);

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'data' => $data,
            'grafolog' => $grafolog,
            'bahasa' => $bahasa,
            't' => trans('laporan', [], $bahasa),
        ]);
        $disk->put($path, $pdf->output());

        foreach ($disk->files('reports') as $file) {
            if (str_starts_with($file, $prefix) && $file !== $path) {
                $disk->delete($file);
            }
        }
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
