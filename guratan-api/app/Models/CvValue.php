<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvValue extends Model
{
    protected $fillable = ['user_id', 'cv_field_id', 'value'];
}
