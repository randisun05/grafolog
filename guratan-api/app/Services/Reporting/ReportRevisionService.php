<?php

namespace App\Services\Reporting;

use App\Models\PersonalityReport;
use App\Models\ReportRevision;
use App\Models\User;

/**
 * Satu titik untuk "simpan snapshot SEBELUM laporan berubah" - dipakai baik
 * oleh koreksi skor (ScoringController::correct) maupun edit narasi manual
 * (ReportController::updateNarasi), supaya keduanya menghasilkan riwayat
 * yang konsisten di `report_revisions` walau jenis perubahannya beda.
 */
class ReportRevisionService
{
    public function snapshotBeforeChange(PersonalityReport $report, string $jenis, ?User $actor, ?string $catatan = null): ReportRevision
    {
        return ReportRevision::create([
            'report_id' => $report->id,
            'jenis' => $jenis,
            'data' => $report->data,
            'catatan' => $catatan,
            'actor_user_id' => $actor?->id,
        ]);
    }

    /**
     * Snapshot khusus narasi terpadu - `data` di revisi jenis ini BUKAN
     * breakdown Sindrom/Aspek (itu tidak berubah lewat alur ini), melainkan
     * {narasi_terpadu, narasi_bahasa, narasi_status} versi sebelum diedit.
     */
    public function snapshotNarasiTerpaduBeforeChange(PersonalityReport $report, ?User $actor, ?string $catatan = null): ReportRevision
    {
        return ReportRevision::create([
            'report_id' => $report->id,
            'jenis' => 'edit_narasi_terpadu',
            'data' => [
                'narasi_terpadu' => $report->narasi_terpadu,
                'narasi_bahasa' => $report->narasi_bahasa,
                'narasi_status' => $report->narasi_status,
            ],
            'catatan' => $catatan,
            'actor_user_id' => $actor?->id,
        ]);
    }
}
