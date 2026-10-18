<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateAnalysis extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['candidate_profile_id', 'job_position_id', 'input_hash', 'summary', 'source', 'model', 'created_by'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
