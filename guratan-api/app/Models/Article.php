<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Artikel informasi (Fase E). `isi` teks polos, paragraf dipisah baris kosong - tidak ada HTML. */
class Article extends Model
{
    protected $fillable = ['judul', 'slug', 'ringkasan', 'isi', 'kategori', 'is_published', 'published_at', 'created_by'];

    protected $attributes = ['is_published' => false, 'kategori' => 'umum'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->where('published_at', '<=', now());
    }
}
