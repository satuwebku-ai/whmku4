# Patch: halaman tidak ada -> kembali ke beranda (domain publik saja)

Salin ke root proyek (timpa), lalu: php artisan optimize:clear
Tidak ada migrasi.

- app/Support/PublicNotFoundRedirect.php (BARU): aturan kapan 404 dialihkan
- bootstrap/app.php: withExceptions -> NotFoundHttpException di domain publik => redirect 302 ke home
- tests/: test baru + 2 test lama disesuaikan (sebelumnya mengharapkan 404 di domain publik)

Tidak dialihkan: subdomain admin/member, POST/JSON/AJAX, file berekstensi
(.jpg/.css/.js/...), dan path teknis (branding, banner-image, vendor, chat, payment, webhook, ...).
