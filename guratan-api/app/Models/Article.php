<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'cover_image_path', 'status', 'published_at', 'created_by',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * URL siap-pakai untuk frontend - `cover_image_path` mentah (path
     * relatif di disk `public`) tidak pernah diekspos langsung lewat API.
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null
        );
    }

    protected $appends = ['cover_image_url'];

    /**
     * Slug digenerate dari title, bukan diketik manual admin - tambah
     * suffix -2/-3/dst kalau sudah dipakai artikel lain.
     */
    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
