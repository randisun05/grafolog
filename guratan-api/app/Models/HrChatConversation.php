<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Percakapan asisten data HR - milik SATU akun HR (tidak dibagi dengan HR lain walau satu perusahaan). */
class HrChatConversation extends Model
{
    protected $fillable = ['user_id', 'company_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HrChatMessage::class, 'conversation_id');
    }
}
