<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelaksanaan satu komponen non-grafologi (wawancara, konsultasi, konseling, intervensi) dari
 * sebuah Order. Admin/HR menugaskan grafolog, grafolog menjadwalkan lalu mengisi laporan sesuai
 * report_fields komponennya. Laporan terlihat oleh klien pemilik (dan HR untuk kandidat B2B).
 */
class ServiceDelivery extends Model
{
    public const STATUS = ['menunggu_penugasan', 'ditugaskan', 'dijadwalkan', 'selesai', 'dibatalkan'];

    protected $fillable = [
        'order_id', 'product_component_id', 'user_id', 'assigned_to', 'status', 'scheduled_at',
        'meeting_url', 'report', 'report_submitted_at',
    ];

    protected $attributes = ['status' => 'menunggu_penugasan'];

    protected function casts(): array
    {
        return [
            'report' => 'array',
            'scheduled_at' => 'datetime',
            'report_submitted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(ProductComponent::class, 'product_component_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isViewableBy(User $user): bool
    {
        return $user->id === $this->user_id
            || $user->id === $this->assigned_to
            || $user->isAdministrator() || $user->isSupervisor()
            || ($user->isHr() && $this->order?->created_by === $user->id);
    }
}
