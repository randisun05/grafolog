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
     *
     * LIST_KEYS (2026-08-07, redesain beranda): subset dari EDITABLE_KEYS
     * yang value-nya JSON-encoded array, bukan string biasa - dipakai section
     * dengan beberapa item berulang (bullet perbandingan, langkah cara kerja,
     * poin kejujuran). Kolom `content_blocks.value` tetap `text` biasa;
     * ini murni konvensi encoding di level aplikasi, bukan perubahan skema.
     * AdminContentView.vue merender field ini sebagai editor daftar kecil,
     * bukan textarea JSON mentah.
     */
    public const EDITABLE_KEYS = [
        'landing_eyebrow',
        'landing_hero_title',
        'landing_tagline',
        'landing_cta_label',
        'landing_hero_trust',
        'landing_compare_heading',
        'landing_compare_subtext',
        'landing_compare_old',
        'landing_compare_new',
        'landing_steps_heading',
        'landing_steps_subtext',
        'landing_steps',
        'landing_analysis_heading',
        'landing_analysis_subtext',
        'landing_pricing_heading',
        'landing_pricing_subtext',
        'landing_honesty_quote',
        'landing_honesty_points',
        'landing_signup_heading',
        'landing_cta_band_heading',
        'landing_cta_band_subtext',
    ];

    public const LIST_KEYS = [
        'landing_compare_old',
        'landing_compare_new',
        'landing_steps',
        'landing_honesty_points',
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
