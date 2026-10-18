<?php

namespace App\Http\Controllers;

use App\Services\Payment\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class Controller
{
    /** Grup metode bayar pilihan pembeli (opsional) - lihat PaymentMethods::resolve(). */
    protected function method(Request $request): ?string
    {
        return $request->validate(['payment_method' => ['nullable', Rule::in(PaymentMethods::keys())]])['payment_method'] ?? null;
    }
}
