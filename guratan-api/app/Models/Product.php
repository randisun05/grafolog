<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Katalog produk/tier (comprehensive/master/dst) - satu sumber kebenaran
 * untuk tier mana yang valid, menggantikan daftar hardcoded
 * `in:comprehensive,master` yang sebelumnya diulang di banyak Form
 * Request/controller. `code` immutable setelah dibuat (lihat
 * UpdateProductRequest - sengaja tidak menerima field `code`) karena
 * bukan foreign key ke `handwriting_samples.tier`/dst, rename akan
 * mengorbankan histori tanpa cara mendeteksinya.
 */
class Product extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    // is_active/sort_order punya default DB, tapi MySQL tidak refetch itu
    // ke model in-memory setelah create() tanpa ini - gotcha yang sama
    // seperti Company/DiscountCode/Announcement, lihat guratan-api/CLAUDE.md.
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Daftar kode tier yang valid dipakai SEKARANG - satu-satunya tempat
     * yang boleh dipanggil untuk validasi tier di seluruh aplikasi
     * (Form Request/controller guard), bukan literal array lagi.
     *
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        return static::where('is_active', true)->orderBy('sort_order')->pluck('code')->all();
    }
}
