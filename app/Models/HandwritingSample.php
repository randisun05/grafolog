<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HandwritingSample extends Model
{
    protected $fillable = [
        'user_id', 'created_by', 'image_path', 'tier', 'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PersonalityReport::class, 'sample_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'sample_id');
    }
}
