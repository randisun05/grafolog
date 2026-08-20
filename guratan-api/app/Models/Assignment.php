<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $fillable = ['sample_id', 'grafolog_id', 'assigned_by', 'status'];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
