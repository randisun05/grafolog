<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Produk mitra eksternal (afiliasi) - ditampilkan dengan label "tautan mitra", klik dicatat. */
class AffiliateProduct extends Model
{
    protected $fillable = ['nama', 'mitra', 'ringkasan', 'image_url', 'harga', 'url', 'kategori', 'target', 'is_active', 'sort', 'created_by'];

    protected $attributes = ['is_active' => true, 'sort' => 0, 'target' => 'semua', 'kategori' => 'lainnya'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }
}
