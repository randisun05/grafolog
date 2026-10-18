<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Variabel CV. `applies_to` = CV siapa yang memuat variabel ini: client (klien & kandidat),
 * grafolog, atau all. `is_hidden` = tidak ditampilkan ke pihak lain (HR, klien, grafolog lain);
 * hanya pemilik yang mengisi dan administrator yang melihat semuanya.
 */
class CvField extends Model
{
    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'boolean', 'email', 'phone', 'photo', 'file'];

    public const APPLIES_TO = ['all', 'client', 'grafolog'];

    protected $fillable = ['cv_group_id', 'key', 'label', 'type', 'options', 'help', 'required', 'is_hidden', 'applies_to', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean', 'is_hidden' => 'boolean', 'is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CvGroup::class, 'cv_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CvValue::class);
    }

    /** Tipe yang nilainya berkas (diunggah lewat endpoint khusus, bukan isian teks). */
    public function isFile(): bool
    {
        return in_array($this->type, ['photo', 'file'], true);
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
