<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvValue extends Model
{
    protected $fillable = ['user_id', 'cv_field_id', 'value'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(CvField::class, 'cv_field_id');
    }
}
