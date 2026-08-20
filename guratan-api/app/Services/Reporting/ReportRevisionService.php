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
}
