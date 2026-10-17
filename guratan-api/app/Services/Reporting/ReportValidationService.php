<?php

namespace App\Services\Reporting;

use App\Models\AuditLog;
use App\Models\PersonalityReport;
use App\Models\TokenCost;
use App\Models\User;
use App\Services\TokenWalletService;
use Illuminate\Support\Facades\DB;

/**
 * Validasi supervisor opsional (Fase D, 2026-10-17). Laporan baru = `draf`. Grafolog memilih:
 * - terbit mandiri -> langsung terlihat klien, ditandai "tanpa validasi supervisor";
 * - minta validasi -> biaya token (TokenCost `validasi_supervisor`, null = gratis) dipotong dari
 *   grafolog, laporan masuk antrean supervisor; token ditransfer ke supervisor yang memberi
 *   keputusan PERTAMA (setuju atau minta revisi). Peninjauan ulang setelah revisi tidak ditagih lagi.
 */
class ReportValidationService
{
    public const COST_KEY = 'validasi_supervisor';

    public const LEDGER_FEE = 'biaya_validasi';

    public const LEDGER_INCOME = 'pendapatan_validasi';

    public function __construct(private TokenWalletService $wallet) {}

    public function fee(): int
    {
        return TokenCost::activeTokensFor(self::COST_KEY) ?? 0;
    }

    public function publish(PersonalityReport $report, User $grafolog, ?string $ip = null): PersonalityReport
    {
        $this->assertDraft($report);
        abort_if($report->validation_requested_at !== null, 422, 'Laporan ini sudah diajukan ke supervisor - tunggu hasil validasinya.');

        $report->update([
            'review_status' => PersonalityReport::REVIEW_APPROVED,
            'validation' => PersonalityReport::VALIDATION_SELF,
            'reviewed_by' => $grafolog->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ]);
        AuditLog::record('terbitkan_laporan_mandiri', PersonalityReport::class, $report->id, $grafolog->id, $ip);

        return $report;
    }

    public function requestValidation(PersonalityReport $report, User $grafolog, ?string $ip = null): PersonalityReport
    {
        $this->assertDraft($report);
        abort_if($report->validation_requested_at !== null, 422, 'Validasi supervisor sudah diajukan.');
        $fee = $this->fee();

        DB::transaction(function () use ($report, $grafolog, $fee) {
            if ($fee > 0) {
                $this->wallet->debit(
                    $grafolog, $fee, ['report_id' => $report->id], self::LEDGER_FEE,
                    "Token tidak cukup untuk validasi supervisor ({$fee} token). Silakan beli token terlebih dahulu.",
                );
            }
            $report->update([
                'review_status' => PersonalityReport::REVIEW_PENDING,
                'validation' => PersonalityReport::VALIDATION_SUPERVISOR,
                'validation_requested_at' => now(),
                'validation_fee' => $fee,
            ]);
        });
        AuditLog::record('minta_validasi_supervisor', PersonalityReport::class, $report->id, $grafolog->id, $ip);

        return $report;
    }

    /** Dipanggil saat supervisor/admin memberi keputusan. Idempoten (sekali bayar per laporan). */
    public function payReviewer(PersonalityReport $report, User $reviewer): void
    {
        if ($report->validation_fee <= 0 || $report->validation_paid_at !== null) {
            return;
        }

        DB::transaction(function () use ($report, $reviewer) {
            $this->wallet->credit($reviewer, $report->validation_fee, ['report_id' => $report->id], self::LEDGER_INCOME);
            $report->update(['validation_paid_to' => $reviewer->id, 'validation_paid_at' => now()]);
        });
    }

    private function assertDraft(PersonalityReport $report): void
    {
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_unless($report->review_status === PersonalityReport::REVIEW_DRAFT, 422, 'Laporan ini tidak berstatus draf.');
    }
}
