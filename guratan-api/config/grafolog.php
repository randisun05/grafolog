<?php

return [
    // Syarat menjadi supervisor: minimal laporan yang sudah TERBIT (disetujui) ditangani grafolog tsb. 0 = tanpa syarat.
    'supervisor_min_reports' => (int) env('SUPERVISOR_MIN_REPORTS', 5),
];
