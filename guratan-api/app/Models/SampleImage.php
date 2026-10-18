<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berkas scan tulisan tangan (PDF; data lama berupa foto) untuk satu sample. Disimpan di disk privat 'local'
 * (bukan 'public') - hanya bisa diambil lewat SampleImageController::show
 * yang mengecek otorisasi dan mencatat audit log.
 */
class SampleImage extends Model
{
    public const DISK = 'local';

    public const MAX_PER_SAMPLE = 10;

    /** Batas unggah (KB). PDF hasil scan 300 dpi bisa besar - semakin jelas semakin baik. */
    public const MAX_KB = 30720;

    protected $fillable = ['sample_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by', 'width', 'height',
        'page_count', 'page_width_mm', 'page_height_mm'];

    protected $hidden = ['path'];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
