<?php

/**
 * Log aktivitas ringan (2026-10-18): satu baris per request tulis (POST/PUT/PATCH/DELETE) milik
 * pengguna yang login + login/logout. TANPA isi request/respons (tidak ada password, data psikologis,
 * atau isi laporan) - hanya siapa, apa (route), kapan, dari mana, hasilnya. Bacaan (GET) opsional.
 * Berbeda dari audit_logs (peristiwa bisnis spesifik, mis. lihat laporan) - keduanya berjalan berdampingan.
 */
return [
    'enabled' => env('ACTIVITY_LOG', true),
    'log_reads' => env('ACTIVITY_LOG_READS', false),
    'retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 90),
    // Endpoint yang terlalu bising / tidak bermakna - tidak dicatat meski log_reads aktif.
    'skip' => ['api/inbox*', 'api/auth/me', 'api/payments/notification', 'api/dashboard', 'api/admin/verification-counts'],
];
