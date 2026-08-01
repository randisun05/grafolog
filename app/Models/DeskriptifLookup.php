<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeskriptifLookup extends Model
{
    protected $table = 'deskriptif_lookup';

    protected $fillable = ['kode', 'keterangan'];

    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }
}
