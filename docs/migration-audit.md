# Migration audit

Tanggal audit: 24 September 2026

## Hasil

- Total file migration setelah konsolidasi: **76**.
- Schema fresh-install sudah memuat hasil akhir dari migration tambahan
  sebelumnya; tidak ada migration tambahan yang harus dijalankan setelah
  baseline ini.
- Tidak ada dua file dengan nama migration lengkap yang sama.
- Ada **2 kelompok timestamp yang sama** (4 file). Ini bukan duplikasi
  migration Laravel: Laravel memakai nama file lengkap sebagai identitas, bukan
  hanya bagian timestamp.
- Fresh database boot dengan seluruh migration berhasil pada SQLite sementara.
- `php artisan test` selesai dengan exit code 0 (**59 test, 185 assertion**);
  PHPUnit menampilkan 58 warning source-inspection dari path workspace test
  yang tidak tersedia di runner ini, tanpa failure.
- PHP lint seluruh `app`, `database`, `routes`, dan `tests` juga lulus.

## Strategi yang dipilih: fresh-install-only

User memilih rewrite langsung ke migration induk untuk instalasi baru saja.
Karena itu migration historis yang hanya berfungsi sebagai alter/rename/cleanup
boleh diserap ke schema final. Database existing **tidak** kompatibel dengan
perubahan history ini dan harus diperlakukan sebagai cutover terpisah.

Yang sudah diserap ke migration induk meliputi:

- lifecycle order dan stock reservation;
- provider VPS, panel type, serta FX rate server;
- tracking provisioning hosting/domain;
- invoice refunded, timestamp paid, tax reference, dan idempotency;
- unique/index payment gateway, coupon usage, invoice item, serta addon;
- nama schema final `cms_pages`, `product_groups`, dan `credits`;
- notification deliveries;
- hourly pricing product;
- seluruh kolom dan tabel hardening affiliate.

Migration cleanup/backfill berikut tidak dipindahkan sebagai query:

`cleanup_*` sebelumnya membatalkan invoice lama berdasarkan status hosting/domain;
database baru tidak memiliki baris lama yang perlu dibackfill. Migration
`rename_*` digantikan dengan nama tabel final langsung. Untuk database existing,
backup dan cutover terencana tetap wajib; migration history lama tidak boleh
dianggap bisa dilanjutkan oleh baseline baru ini.

## Audit keamanan endpoint

- Route admin dan client yang terdaftar semuanya berada di bawah middleware
  guard yang sesuai.
- Controller client menggunakan policy ownership untuk invoice, payment,
  domain, hosting account, ticket, dokumen domain, dan addon.
- Ditemukan satu masalah: bukti transfer sebelumnya disimpan di disk `public`,
  sehingga file di bawah `public/storage/payment-proofs/...` berpotensi dibaca
  tanpa login walaupun route Laravel-nya memakai authorization.
- Upload baru sekarang disimpan di disk `local` dan endpoint admin/client
  membaca dari disk private.
- Untuk deployment yang sudah memiliki bukti lama di disk public, jalankan
  `php artisan lumora:migrate-payment-proofs --dry-run`, lalu
  `php artisan lumora:migrate-payment-proofs` setelah backup. Command tersebut
  memindahkan file dengan path yang sama dan menghapus salinan public.

Race condition provider/payment yang tersisa perlu diuji terhadap gateway nyata
karena lock database tidak boleh ditahan selama HTTP call ke provider. Test
SQLite saat ini memverifikasi ledger dan replay idempotency, tetapi tidak dapat
mensimulasikan dua request HTTP provider secara bersamaan.

### Inisialisasi payment dan QRIS

- Pembuatan payment admin sekarang memakai lock invoice + transaksi database,
  sehingga pola check-then-insert tidak dapat membuat dua payment aktif dari
  request paralel.
- Call ke provider untuk client/admin memakai lock cache invoice + gateway.
  Request kedua menggunakan `external_id`/URL yang sudah tersimpan, bukan
  membuat transaksi provider kedua.
- QRIS sebelumnya membuat payment langsung dari request GET. Route tersebut
  sekarang POST + CSRF karena inisialisasi QRIS adalah operasi yang mengubah
  state dan memanggil provider eksternal.
- QRIS memakai lock yang sama, mencari payment aktif yang sudah ada, dan tidak
  mengubah payment manual `pending` menjadi transaksi QRIS baru.

## Audit operasi domain dan VPS

- Aksi VPS client yang mengubah state provider (`start`, `stop`, `restart`,
  `force_stop`, `change password`, `reinstall`, dan `resize`) sekarang
  diserialkan dengan lock per hosting account. State lokal diperbarui di dalam
  lock setelah provider menerima perintah.
- Registrar Lock, Theft Protection, dan nameserver memakai lock per domain.
  Operasi yang membaca status lalu melakukan toggle tidak lagi dapat saling
  membalikkan akibat dua request paralel.
- Pembuatan invoice ID Protection memakai `lockForUpdate()` sehingga klik
  ganda tidak membuat dua invoice untuk satu domain.
- Permintaan kode transfer/EPP mengunci baris domain sebelum mencari tiket dan
  membuat tiket baru. Toggle auto-renew, pengajuan/penarikan pembatalan,
  pembatalan upgrade, pembatalan addon, dan upload/hapus dokumen juga
  memeriksa ulang state di dalam lock.
- Renewal hosting/domain dan upgrade/addon sudah memakai service yang mengunci
  resource dengan `lockForUpdate()` sebelum audit ini, sehingga tidak diubah.
- Follow-up setelah invoice lunas untuk ID Protection, addon, upgrade, dan
  renewal kini memakai lock per invoice selama HTTP call/provider update.
  Retry paralel tidak lagi mengirim dua perubahan provider untuk invoice yang
  sama.
- Endpoint DNS, forwarding, dan email forwarding sekarang juga diserialkan
  per domain, tetapi tetap bergantung pada idempotency/error handling registrar
  karena sebagian provider tidak menyediakan idempotency key. Jika proses mati
  tepat setelah provider menerima request tetapi sebelum respons tersimpan,
  rekonsiliasi provider nyata masih diperlukan.