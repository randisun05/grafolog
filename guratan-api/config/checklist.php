<?php

return [
    // Indikator bernama sama di aspek lain ikut tercentang (sumber 'ciri_bersama').
    // Matikan bila grafolog memutuskan ciri bernama sama tidak selalu identik.
    'ciri_bersama' => (bool) env('CHECKLIST_CIRI_BERSAMA', true),
];
