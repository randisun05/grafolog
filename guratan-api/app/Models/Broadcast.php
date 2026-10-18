<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $fillable = ['title', 'body', 'target_roles', 'send_email', 'recipients_count', 'created_by'];

    protected function casts(): array
    {
        return ['target_roles' => 'array', 'send_email' => 'boolean'];
    }
}
