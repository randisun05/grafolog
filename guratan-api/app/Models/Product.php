<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produk Guratan (2026-10-17). Harga aktif disimpan di pricing_plans dengan `tier` = kode produk
 * (PricingPlan::activePriceFor/setPriceFor), jadi riwayat harga dan kode diskon lama (yang
 * memakai 'master') tetap berlaku tanpa tabel harga kedua.
 */
class Product extends Model
{
    public const KATEGORI = ['utama', 'pendukung'];

    public const TARGET = ['b2c', 'b2b', 'semua'];

    public const MODES = ['online', 'offline'];

    public const COMMISSION_TYPES = ['percent', 'fixed'];

    protected $fillable = [
        'kode', 'nama', 'kategori', 'ringkasan', 'deskripsi', 'target', 'requires_handwriting',
        'is_active', 'is_featured', 'promo_label', 'sort', 'created_by', 'modes', 'requirements', 'token_cost', 'commission_type', 'commission_value',
    ];

    protected $attributes = [
        'kategori' => 'pendukung', 'target' => 'semua', 'requires_handwriting' => false,
        'is_active' => true, 'is_featured' => false, 'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'requires_handwriting' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'modes' => 'array',
            'requirements' => 'array',
        ];
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ProductCommission::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class)->orderBy('sort');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function currentPrice(): ?int
    {
        return PricingPlan::activePriceFor($this->kode);
    }

    public function hasGraphology(): bool
    {
        return $this->components->contains('tipe', 'grafologi');
    }

    /** Produk yang boleh dibeli klien individu (B2C). */
    public function scopeForClients(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereIn('target', ['b2c', 'semua']);
    }

    /** Produk yang boleh dipesan perusahaan (B2B). */
    public function scopeForCompanies(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereIn('target', ['b2b', 'semua']);
    }

    /** Mode pelaksanaan yang ditawarkan (default online saja). */
    public function availableModes(): array
    {
        return $this->modes ?: ['online'];
    }

    public function toCatalogArray(): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'kategori' => $this->kategori,
            'ringkasan' => $this->ringkasan,
            'deskripsi' => $this->deskripsi,
            'target' => $this->target,
            'requires_handwriting' => $this->requires_handwriting,
            'is_featured' => $this->is_featured,
            'promo_label' => $this->promo_label,
            'modes' => $this->availableModes(),
            'requirements' => $this->requirements ?? [],
            'price' => $this->currentPrice(),
            'components' => $this->components->map(fn ($c) => ['tipe' => $c->tipe, 'nama' => $c->nama])->values(),
        ];
    }
}
