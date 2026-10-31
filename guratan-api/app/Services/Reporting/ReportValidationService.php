<?php

namespace App\Services\Reporting;

use App\Models\AuditLog;
use App\Models\GrafologAvailability;
use App\Models\PersonalityReport;
use App\Models\ReportValidation;
use App\Models\TokenCost;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\TokenWalletService;
use Illuminate\Support\Facades\DB;

/**
 * Supervisi laporan antar-grafolog (2026-10-18, menggantikan supervisor khusus).
 * Laporan baru = `draf`. Pembuat laporan memilih: terbitkan sendiri, atau tugaskan grafolog lain
 * (yang bersedia supervisi di bulan itu) dengan tenggat hari pilihannya.
 * Token (TokenCost `validasi_supervisor`, null = gratis) DITAHAN dari pembuat saat penugasan:
 * - validator menolak penugasan / lewat tenggat -> token dikembalikan, laporan kembali draf (bisa ditugaskan ulang);
 * - validator menyetujui atau minta revisi -> pembuat mengonfirmasi (atau otomatis setelah AUTO_CONFIRM_DAYS)
 *   -> token cair ke validator; setujui = laporan terbit, revisi = laporan `perlu_revisi`.
 * Admin tidak menerima token, hanya memantau penugasan.
 */
class ReportValidationService
{
    public const COST_KEY = 'validasi_supervisor';

    public const LEDGER_FEE = 'biaya_validasi';

    public const LEDGER_REFUND = 'pengembalian_validasi';

    public const LEDGER_INCOME = 'pendapatan_validasi';

    public const MAX_DEADLINE_DAYS = 30;

    public const AUTO_CONFIRM_DAYS = 3;

    public function __construct(private TokenWalletService $wallet, private Notifier $notifier) {}

    public function fee(): int
    {
        return TokenCost::activeTokensFor(self::COST_KEY) ?? 0;
    }

    /** Grafolog yang bisa dipilih sebagai validator bulan ini. */
    public function validatorsFor(User $requester)
    {
        $month = now()->format('Y-m');

        return User::where('role', 'grafolog')->where('id', '!=', $requester->id)
            ->where(fn ($q) => $q->whereNull('grafolog_status')->orWhere('grafolog_status', 'verified'))
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'bio', 'specializations', 'experience_years'])
            ->filter(fn (User $g) => GrafologAvailability::forMonth($g, $month)['supervisable'] && $this->meetsSupervisorMinimum($g))
            ->values();
    }

    /** Syarat supervisor: sudah menerbitkan minimal N laporan (config/grafolog.php). */
    public function meetsSupervisorMinimum(User $g): bool
    {
        $min = (int) config('grafolog.supervisor_min_reports');

        return $min <= 0 || PersonalityReport::where('review_status', PersonalityReport::REVIEW_APPROVED)
            ->whereHas('sample', fn ($q) => $q->where('created_by', $g->id)->orWhereHas('assignment', fn ($a) => $a->where('grafolog_id', $g->id)))->count() >= $min;
    }

    public function publish(PersonalityReport $report, User $grafolog, ?string $ip = null): PersonalityReport
    {
        $this->assertDecidable($report);

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

    public function request(PersonalityReport $report, User $requester, User $validator, int $days, ?string $ip = null): ReportValidation
    {
        $this->assertDecidable($report);
        abort_unless($days >= 1 && $days <= self::MAX_DEADLINE_DAYS, 422, 'Tenggat harus 1-'.self::MAX_DEADLINE_DAYS.' hari.');
        abort_if($validator->id === $requester->id, 422, 'Tidak bisa menugaskan diri sendiri.');
        abort_if($report->sample->isScorableBy($validator), 422, 'Validator harus grafolog lain, bukan yang menangani sampel ini.');
        abort_unless(
            $validator->isGrafolog() && GrafologAvailability::forMonth($validator, now()->format('Y-m'))['supervisable'] && $this->meetsSupervisorMinimum($validator),
            422,
            'Grafolog ini tidak tersedia untuk supervisi bulan ini (atau belum memenuhi syarat supervisor).',
        );
        $fee = $this->fee();

        $validation = DB::transaction(function () use ($report, $requester, $validator, $days, $fee) {
            if ($fee > 0) {
                $this->wallet->debit(
                    $requester, $fee, ['report_id' => $report->id], self::LEDGER_FEE,
                    "Token tidak cukup untuk supervisi ({$fee} token). Silakan beli token terlebih dahulu.",
                );
            }
            $report->update(['review_status' => PersonalityReport::REVIEW_PENDING, 'validation' => null]);

            return $report->validations()->create([
                'requester_id' => $requester->id, 'validator_id' => $validator->id,
                'fee' => $fee, 'deadline_days' => $days, 'due_at' => now()->addDays($days),
            ]);
        });
        AuditLog::record('tugaskan_supervisi', ReportValidation::class, $validation->id, $requester->id, $ip);
        $this->notifier->send(
            $validator, 'supervisi_ditugaskan', 'Penugasan supervisi laporan',
            "{$requester->name} meminta Anda memvalidasi laporan #{$report->id}. Tenggat {$days} hari ({$validation->due_at->translatedFormat('d M Y')}).",
            '/grafolog/supervisi',
        );

        return $validation;
    }

    public function decline(ReportValidation $v, User $validator, ?string $note, ?string $ip = null): ReportValidation
    {
        $this->assertValidatorTurn($v, $validator);
        $this->close($v, ReportValidation::DECLINED, $note);
        AuditLog::record('tolak_penugasan_supervisi', ReportValidation::class, $v->id, $validator->id, $ip);
        $this->notifier->send($v->requester, 'supervisi_ditolak', 'Penugasan supervisi ditolak',
            "{$validator->name} menolak penugasan supervisi laporan #{$v->report_id}. Token dikembalikan - tugaskan grafolog lain.",
            "/reports/{$v->report_id}");

        return $v;
    }

    public function decide(ReportValidation $v, User $validator, string $decision, ?string $note, ?string $ip = null): ReportValidation
    {
        $this->assertValidatorTurn($v, $validator);
        $approve = $decision === 'approve';
        abort_if(! $approve && blank($note), 422, 'Catatan wajib diisi saat meminta revisi.');

        $v->update([
            'status' => $approve ? ReportValidation::APPROVED : ReportValidation::REVISION,
            'note' => $note, 'decided_at' => now(),
        ]);
        AuditLog::record($approve ? 'supervisi_disetujui' : 'supervisi_minta_revisi', ReportValidation::class, $v->id, $validator->id, $ip);
        $this->notifier->send($v->requester, 'supervisi_selesai', 'Hasil supervisi laporan siap',
            "{$validator->name} ".($approve ? 'menyetujui' : 'meminta revisi')." laporan #{$v->report_id}. Konfirmasi hasilnya untuk mengkreditkan token ke validator.",
            "/reports/{$v->report_id}");

        return $v;
    }

    /** Pembuat laporan mengonfirmasi hasil: token cair, laporan terbit (setuju) atau perlu_revisi. */
    public function confirm(ReportValidation $v, User $requester, ?string $ip = null, bool $auto = false): ReportValidation
    {
        abort_unless($auto || $v->requester_id === $requester->id, 403);
        abort_unless(in_array($v->status, [ReportValidation::APPROVED, ReportValidation::REVISION], true), 422, 'Belum ada hasil supervisi untuk dikonfirmasi.');
        $approved = $v->status === ReportValidation::APPROVED;

        DB::transaction(function () use ($v, $approved) {
            if ($v->fee > 0) {
                $this->wallet->credit($v->validator, $v->fee, ['report_id' => $v->report_id], self::LEDGER_INCOME);
            }
            $v->report->update($approved ? [
                'review_status' => PersonalityReport::REVIEW_APPROVED,
                'validation' => PersonalityReport::VALIDATION_SUPERVISOR,
                'reviewed_by' => $v->validator_id, 'reviewed_at' => now(), 'review_note' => $v->note,
            ] : [
                'review_status' => PersonalityReport::REVIEW_REVISION,
                'validation' => null,
                'reviewed_by' => $v->validator_id, 'reviewed_at' => now(), 'review_note' => $v->note,
            ]);
            $v->update(['status' => ReportValidation::DONE, 'confirmed_at' => now(), 'paid_at' => now(),
                'note' => $v->note]);
        });
        AuditLog::record($auto ? 'supervisi_dikonfirmasi_otomatis' : 'supervisi_dikonfirmasi', ReportValidation::class, $v->id, $auto ? null : $requester->id, $ip);
        $this->notifier->send($v->validator, 'supervisi_dibayar', 'Token supervisi cair',
            $v->fee > 0 ? "{$v->fee} token dari supervisi laporan #{$v->report_id} masuk ke saldo Anda." : "Supervisi laporan #{$v->report_id} dikonfirmasi.",
            '/token-saya');

        return $v;
    }

    /**
     * Tutup penugasan yang lewat tenggat (token kembali) dan konfirmasi otomatis hasil yang
     * dibiarkan > AUTO_CONFIRM_DAYS hari. Dipanggil terjadwal + saat daftar dibuka.
     */
    public function sweep(): int
    {
        $n = 0;
        ReportValidation::where('status', ReportValidation::WAITING)->where('due_at', '<', now())->get()
            ->each(function (ReportValidation $v) use (&$n) {
                $this->close($v, ReportValidation::EXPIRED, 'Melewati tenggat.');
                AuditLog::record('supervisi_kedaluwarsa', ReportValidation::class, $v->id, null, null);
                $this->notifier->send($v->requester, 'supervisi_kedaluwarsa', 'Supervisi melewati tenggat',
                    "Penugasan supervisi laporan #{$v->report_id} melewati tenggat. Token dikembalikan - tugaskan ulang.",
                    "/reports/{$v->report_id}");
                $this->notifier->send($v->validator, 'supervisi_kedaluwarsa', 'Penugasan supervisi kedaluwarsa',
                    "Penugasan supervisi laporan #{$v->report_id} melewati tenggat dan ditutup.", '/grafolog/supervisi');
                $n++;
            });

        ReportValidation::whereIn('status', [ReportValidation::APPROVED, ReportValidation::REVISION])
            ->where('decided_at', '<', now()->subDays(self::AUTO_CONFIRM_DAYS))->get()
            ->each(function (ReportValidation $v) use (&$n) {
                $this->confirm($v, $v->requester, null, true);
                $n++;
            });

        return $n;
    }

    /** Tutup tanpa hasil: token kembali ke pembuat, laporan kembali ke draf. */
    private function close(ReportValidation $v, string $status, ?string $note): void
    {
        DB::transaction(function () use ($v, $status, $note) {
            if ($v->fee > 0) {
                $this->wallet->credit($v->requester, $v->fee, ['report_id' => $v->report_id], self::LEDGER_REFUND);
            }
            $v->update(['status' => $status, 'note' => $note, 'decided_at' => now()]);
            $v->report->update(['review_status' => PersonalityReport::REVIEW_DRAFT, 'validation' => null]);
        });
    }

    private function assertDecidable(PersonalityReport $report): void
    {
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_if($report->hasActiveValidation(), 422, 'Laporan ini sedang dalam proses supervisi.');
        abort_unless(
            in_array($report->review_status, [PersonalityReport::REVIEW_DRAFT, PersonalityReport::REVIEW_REVISION], true),
            422,
            'Laporan ini tidak berstatus draf.',
        );
    }

    private function assertValidatorTurn(ReportValidation $v, User $validator): void
    {
        abort_unless($v->validator_id === $validator->id, 403, 'Penugasan ini bukan untuk Anda.');
        abort_unless($v->status === ReportValidation::WAITING, 422, 'Penugasan ini sudah tidak menunggu keputusan.');
        abort_if($v->due_at->isPast(), 422, 'Penugasan ini sudah melewati tenggat.');
    }
}
