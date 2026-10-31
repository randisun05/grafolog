<?php

return [
    // Mode uji tanpa DOKU: pembayaran langsung dianggap lunas. null = otomatis
    // (aktif bila DOKU_CLIENT_ID kosong). Diabaikan di APP_ENV=production.
    // Lihat App\Services\Payment\PaymentMode.
    'bypass' => env('PAYMENT_BYPASS'),

    // Gateway default ('doku' | 'xendit'). Pembeli boleh memilih gateway lain yang kredensialnya
    // terisi (field `gateway` di request bayar; daftar di GET /api/payment-gateways).
    'gateway' => env('PAYMENT_GATEWAY', 'doku'),
];
