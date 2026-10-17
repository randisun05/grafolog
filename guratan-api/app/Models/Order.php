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

    protected $fillable = [
        'batch_id', 'user_id', 'product_id', 'sample_id', 'grafolog_id', 'status', 'base_amount', 'amount', 'discount_code_id',
        'source', 'created_by', 'paid_at', 'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected $attributes = ['status' => self::STATUS_UNPAID, 'source' => 'website'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
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
}
