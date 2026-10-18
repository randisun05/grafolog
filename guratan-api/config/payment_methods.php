<?php

/**
 * Grup metode bayar yang bisa dipilih pembeli / dibatasi oleh kode promo. `types` = nilai
 * payment.payment_method_types pada DOKU Checkout (cek daftar terbaru di dokumentasi DOKU
 * sebelum produksi - nama channel bisa bertambah/berubah).
 */
return [
    'va' => ['label' => 'Virtual Account', 'types' => [
        'VIRTUAL_ACCOUNT_BCA', 'VIRTUAL_ACCOUNT_BANK_MANDIRI', 'VIRTUAL_ACCOUNT_BRI', 'VIRTUAL_ACCOUNT_BNI',
        'VIRTUAL_ACCOUNT_BANK_PERMATA', 'VIRTUAL_ACCOUNT_BANK_CIMB', 'VIRTUAL_ACCOUNT_DOKU',
    ]],
    'ewallet' => ['label' => 'E-Wallet', 'types' => ['EMONEY_SHOPEEPAY', 'EMONEY_OVO', 'EMONEY_DANA', 'DOKU_WALLET']],
    'qris' => ['label' => 'QRIS', 'types' => ['QRIS']],
    'card' => ['label' => 'Kartu Kredit', 'types' => ['CREDIT_CARD']],
    'retail' => ['label' => 'Gerai Retail', 'types' => ['ONLINE_TO_OFFLINE_ALFA', 'ONLINE_TO_OFFLINE_INDOMARET']],
];
