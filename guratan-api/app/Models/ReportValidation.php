<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu penugasan supervisi laporan (2026-10-18): pembuat laporan menugaskan grafolog lain sebagai
 * validator dengan tenggat hari. Token ditahan dari pembuat dan cair ke validator setelah pembuat
 * mengonfirmasi hasilnya. Lihat App\Services\Reporting\ReportValidationService.
 */
class ReportValidation extends Model
{
    public const WAITING = 'menunggu';

    public const APPROVED = 'disetujui';

    public const REVISION = 'perlu_revisi';

    public const DONE = 'selesai';

    public const DECLINED = 'ditolak';

    public const EXPIRED = 'kedaluwarsa';

    /** Status yang masih "hidup" - laporan terkunci dari edit & tidak bisa diterbitkan/diajukan ulang. */
    public const ACTIVE = [self::WAITING, self::APPROVED, self::REVISION];

    protected $fillable = [
        'report_id', 'requester_id', 'validator_id', 'status', 'fee', 'deadline_days', 'due_at',
        'note', 'decided_at', 'confirmed_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'decided_at' => 'datetime', 'confirmed_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(PersonalityReport::class, 'report_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::WAITING && $this->due_at->isPast();
    }
}
