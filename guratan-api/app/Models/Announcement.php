<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'body', 'target_roles', 'starts_at', 'ends_at', 'is_active', 'created_by',
    ];

    /**
     * Mirror the DB default here too - same MySQL create()-doesn't-refetch-
     * defaults gotcha as DiscountCode (see guratan-api/CLAUDE.md's
     * "Discount codes" section). Without this, a freshly created
     * Announcement's isVisibleTo() would incorrectly return false until
     * ->fresh().
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Single source of truth for "should this user see this banner right
     * now" - active, inside its time window, and either untargeted
     * (null = everyone) or the user's role is in the target list.
     */
    public function isVisibleTo(User $user): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        if ($this->target_roles !== null && ! in_array($user->role, $this->target_roles, true)) {
            return false;
        }

        return true;
    }
}
