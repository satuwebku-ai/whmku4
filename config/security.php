<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Security Policy rollout
    |--------------------------------------------------------------------------
    |
    | Keep the report-only phase enabled until the collected violations have
    | been reviewed. Enforcing CSP should be a separate, verified deployment.
    |
    */
    'csp_report_only' => env('CSP_REPORT_ONLY', true),
    'csp_report_uri' => env('CSP_REPORT_URI', '/csp-report'),
    // Semua event handler inline di view sudah dimigrasi ke data-* (partials/csp-actions).
    // true = izinkan lagi (script-src-attr 'unsafe-inline'); hanya untuk darurat/rollback.
    'csp_allow_inline_handlers' => env('CSP_ALLOW_INLINE_HANDLERS', false),

    /*
    |--------------------------------------------------------------------------
    | Backup signing key
    |--------------------------------------------------------------------------
    |
    | Kunci khusus penandatangan backup (min. 32 byte; boleh berformat
    | "base64:..."). Kosong = memakai APP_KEY. Saat mengganti kunci, pindahkan
    | kunci lama ke BACKUP_SIGNING_KEY_PREVIOUS (pisahkan dengan koma) agar
    | backup lama tetap bisa diverifikasi.
    |
    */
    'backup_signing_key' => env('BACKUP_SIGNING_KEY'),
    'backup_signing_key_previous' => env('BACKUP_SIGNING_KEY_PREVIOUS'),
];
