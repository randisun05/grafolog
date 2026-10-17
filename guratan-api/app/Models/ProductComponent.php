<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu layanan di dalam produk. `grafologi` dikerjakan lewat handwriting_sample + PersonalityReport;
 * tipe lain menjadi ServiceDelivery dengan laporan dari `report_fields`
 * ([{key, label, type: textarea|score|choice, options?}] - score = 1..5).
 */
class ProductComponent extends Model
{
    public const TIPE = ['grafologi', 'wawancara', 'konsultasi', 'konseling', 'intervensi', 'lainnya'];

    protected $fillable = ['product_id', 'tipe', 'nama', 'deskripsi', 'report_fields', 'sort'];

    protected function casts(): array
    {
        return ['report_fields' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
