<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider', 'model', 'purpose', 'status', 'key_slot', 'http_status', 'error',
        'prompt_tokens', 'output_tokens', 'cost_usd', 'latency_ms',
    ];

    protected function casts(): array
    {
        return ['cost_usd' => 'float', 'created_at' => 'datetime'];
    }
}
