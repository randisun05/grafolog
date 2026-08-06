<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlock extends Model
{
    /**
     * Field tetap yang boleh diedit lewat panel admin (Keputusan bisnis
     * 2026-08-06: bukan editor bebas gaya page-builder). Kalau butuh field
     * baru, tambah key di sini DAN default-nya di
     * database/seeders/ContentBlockSeeder.php - dua-duanya, jangan salah satu.
     */
    public const EDITABLE_KEYS = [
        'landing_eyebrow',
        'landing_tagline',
        'landing_cta_label',
    ];

    protected $fillable = ['key', 'value', 'updated_by'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function valueFor(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}
