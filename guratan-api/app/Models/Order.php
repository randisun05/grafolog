<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pembelian satu produk (2026-10-17). Status: menunggu_pembayaran -> diproses (lunas; layanan
 * pendukung dibuat) -> selesai (laporan grafologi disetujui & semua layanan dilaporkan), atau
 * dibatalkan. Lihat App\Services\Commerce\OrderService.
 */
class Order extends Model
{
    public const STATUS_UNPAID = 'menunggu_pembayaran';

    public const STATUS_PROCESSING = 'diproses';

    public const STATUS_DONE = 'selesai';

    public const STATUS_CANCELLED = 'dibatalkan';

    /** Dana dikembalikan setelah admin menyetujui permintaan refund (SOP Fase E). */
    public const STATUS_REFUNDED = 'dikembalikan';

    /** Refund hanya oleh admin: kesalahan sistem, atau pesanan belum selesai setelah sekian hari. */
    public const REFUND_OVERDUE_DAYS = 90;

    protected $fillable = [
        'batch_id', 'user_id', 'product_id', 'sample_id', 'grafolog_id', 'status', 'base_amount', 'amount', 'discount_code_id',
        'source', 'created_by', 'delivery_mode', 'scheduled_for', 'upload_deadline', 'location_note', 'grafolog_choice', 'grafolog_state', 'grafolog_deadline', 'grafolog_attempts', 'paid_at', 'completed_at', 'cancelled_at', 'cancel_reason',
        'refund_requested_at', 'refund_reason', 'refund_status', 'refund_note', 'refund_decided_by', 'refunded_at',
    ];

    protected $attributes = ['status' => self::STATUS_UNPAID, 'source' => 'website'];

    protected function casts(): array
    {
        return [
            'grafolog_deadline' => 'datetime',
            'scheduled_for' => 'date',
            'upload_deadline' => 'date',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refund_requested_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(GrafologEarning::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class);
    }

    public function grafologRequests(): HasMany
    {
        return $this->hasMany(GrafologRequest::class)->latest('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OrderBatch::class, 'batch_id');
    }

    /** Grafolog pilihan pemesan (HR) - ditugaskan otomatis saat lunas. */
    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ServiceDelivery::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** Pesanan dibayar & belum selesai lebih dari 90 hari - bisa di-refund admin (grafolog terlambat). */
    public function isOverdueForRefund(): bool
    {
        return $this->status === self::STATUS_PROCESSING
            && $this->paid_at !== null
            && $this->paid_at->lt(now()->subDays(self::REFUND_OVERDUE_DAYS));
    }
}
