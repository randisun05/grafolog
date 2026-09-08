<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Percakapan chat interaktif Supervisor (fitur Supervisor Fase 3,
 * 2026-09-08) - milik SATU Supervisor, TIDAK dibagi antar-Supervisor
 * sekalipun 1 company. Lihat CLAUDE.md "Peran Supervisor" untuk konteks
 * penuh dan kenapa `company_id` disalin, bukan live-join.
 */
class SupervisorChatConversation extends Model
{
    protected $fillable = ['user_id', 'company_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupervisorChatMessage::class, 'conversation_id');
    }
}
