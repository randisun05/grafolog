<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pesan (user atau assistant) dalam SupervisorChatConversation - lihat
 * CLAUDE.md "Peran Supervisor". Append-only (sama pola AuditLog).
 */
class SupervisorChatMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'token_usage'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupervisorChatConversation::class, 'conversation_id');
    }
}
