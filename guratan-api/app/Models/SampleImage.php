<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto tulisan tangan untuk satu sample. Disimpan di disk privat 'local'
 * (bukan 'public') - hanya bisa diambil lewat SampleImageController::show
 * yang mengecek otorisasi dan mencatat audit log.
 */
class SampleImage extends Model
{
    public const DISK = 'local';

    public const MAX_PER_SAMPLE = 10;

    /** Batas unggah (KB). Hasil scan resolusi tinggi bisa besar - semakin jelas semakin baik. */
    public const MAX_KB = 30720;

    /** Resolusi minimum: sisi panjang >= 1920 px dan sisi pendek >= 1080 px (FHD, orientasi bebas). */
    public const MIN_LONG_SIDE = 1920;

    public const MIN_SHORT_SIDE = 1080;

    protected $fillable = ['sample_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by', 'width', 'height'];

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
