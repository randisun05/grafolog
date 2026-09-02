<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan pendaftaran grafolog lewat verifikasi data - lihat migrasi
 * `create_grafolog_applications_table` untuk konteks keputusan produk.
 */
class GrafologApplication extends Model
{
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'catatan',
        'document_path', 'document_original_name', 'status',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
