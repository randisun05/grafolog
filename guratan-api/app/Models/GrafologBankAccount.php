<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rekening tujuan pencairan komisi. Nomor rekening terenkripsi di database. */
class GrafologBankAccount extends Model
{
    protected $fillable = ['user_id', 'bank_name', 'account_number', 'account_holder'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted'];
    }

    public function masked(): string
    {
        $n = (string) $this->account_number;

        return str_repeat('•', max(0, strlen($n) - 4)).substr($n, -4);
    }
}
