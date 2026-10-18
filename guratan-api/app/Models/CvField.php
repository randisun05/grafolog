<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Variabel CV. `visible_to` mengatur siapa SELAIN pemilik yang boleh membaca isinya:
 * all = semua pihak berwenang, client = pemilik + HR perusahaan (grafolog tidak melihat),
 * grafolog = pemilik + grafolog yang menangani (HR tidak melihat). Pemilik selalu melihat & mengisi semuanya.
 */
class CvField extends Model
{
    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'boolean', 'email', 'phone'];

    public const VISIBILITY = ['all', 'client', 'grafolog'];

    protected $fillable = ['cv_group_id', 'key', 'label', 'type', 'options', 'help', 'required', 'visible_to', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CvGroup::class, 'cv_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CvValue::class);
    }

    /** Aturan validasi Laravel untuk isian bertipe ini. */
    public function rules(): array
    {
        $base = [$this->required ? 'required' : 'nullable'];

        return match ($this->type) {
            'number' => [...$base, 'numeric'],
            'date' => [...$base, 'date'],
            'email' => [...$base, 'email', 'max:150'],
            'phone' => [...$base, 'string', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'boolean' => [...$base, 'boolean'],
            'select' => [...$base, 'in:'.implode(',', array_map(fn ($o) => str_replace(',', ' ', $o), $this->options ?? []))],
            'textarea' => [...$base, 'string', 'max:2000'],
            default => [...$base, 'string', 'max:255'],
        };
    }
}
