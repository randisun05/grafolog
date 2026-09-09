<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'cover_image_path', 'location', 'is_online',
        'starts_at', 'ends_at', 'capacity', 'status', 'created_by',
    ];

    protected $attributes = [
        'status' => 'draft',
        'is_online' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null
        );
    }

    /**
     * `null` = tanpa batas kuota. Cuma menghitung baris `registered` -
     * baris `cancelled` tidak memakan kuota.
     */
    protected function spotsRemaining(): Attribute
    {
        return Attribute::get(function () {
            if ($this->capacity === null) {
                return null;
            }

            return max(0, $this->capacity - $this->registrations()->where('status', 'registered')->count());
        });
    }

    protected $appends = ['cover_image_url', 'spots_remaining'];

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
