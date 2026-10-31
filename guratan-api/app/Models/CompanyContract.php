<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kontrak custom sales-led per perusahaan (B2B Fase 3) - record-only,
 * TIDAK menghitung tagihan/kalkulasi apa pun. Lihat migrasi untuk
 * konteks keputusan produk lengkap.
 */
class CompanyContract extends Model
{
    protected $fillable = [
        'company_id', 'judul', 'catatan', 'nilai_kontrak', 'mulai_at', 'berakhir_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'nilai_kontrak' => 'decimal:2',
            // Format eksplisit Y-m-d (bukan cast 'date' polos) - tanpa ini
            // JSON serialize jadi timestamp ISO penuh ("2026-01-01T00:00:00.
            // 000000Z"), padahal ini genuinely tanggal bukan waktu, dan
            // frontend menampilkannya apa adanya.
            'mulai_at' => 'date:Y-m-d',
            'berakhir_at' => 'date:Y-m-d',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
