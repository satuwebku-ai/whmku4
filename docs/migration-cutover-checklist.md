# Checklist cutover ke migration baseline fresh-install

Dokumen ini berlaku karena migration project sudah di-rewrite untuk
**fresh-install-only**. Baseline baru **tidak boleh** dijalankan dengan
`migrate:fresh` pada database production yang berisi data.

## 1. Persiapan

- [ ] Bekukan deployment dan perubahan data selama window cutover.
- [ ] Catat versi commit/ZIP aplikasi lama dan baseline baru.
- [ ] Pastikan backup database bisa direstore di environment terpisah.
- [ ] Backup storage aplikasi, terutama dokumen domain dan bukti pembayaran.
- [ ] Simpan konfigurasi deployment lama secara aman; jangan memasukkan secret
      ke ZIP atau repository.
- [ ] Siapkan database baru yang kosong dengan engine dan collation yang sama
      dengan target production.

## 2. Bangun baseline baru

- [ ] Deploy source baseline baru ke environment staging.
- [ ] Isi environment staging dengan konfigurasi non-production.
- [ ] Jalankan `php artisan migrate:fresh --force` **hanya pada database kosong**.
- [ ] Jalankan seeder yang memang diperlukan.
- [ ] Jalankan test suite dan smoke test login, checkout, pembayaran, domain,
      hosting, VPS, notifikasi, dan admin.
- [ ] Verifikasi tabel final: `cms_pages`, `product_groups`, `credits`,
      `notification_deliveries`, serta tabel hardening affiliate.

## 3. Migrasi data

Gunakan skrip ETL satu kali yang dapat diuji dan dihentikan, bukan migration
Laravel biasa. Skrip harus memiliki mapping dan laporan jumlah baris:

Sebelum ETL dibuat dan direview, gunakan pemeriksaan read-only:

```bash
php scripts/legacy-baseline-dry-run.php \
  --source=/path/to/legacy.sqlite
```

Tambahkan `--json` bila hasilnya perlu disimpan sebagai artefak audit. Perintah
ini tidak menjalankan `INSERT`, `UPDATE`, `DELETE`, DDL, atau transaksi write.

Setelah plan direview dan target baru sudah dibuat dengan `migrate:fresh`,
ETL dapat dijalankan pada target SQLite kosong:

```bash
php scripts/legacy-baseline-etl.php \
  --source=/path/to/legacy.sqlite \
  --target=/path/to/baseline.sqlite \
  --apply \
  --json
```

ETL akan berhenti jika target tidak kosong, kolom wajib tidak tersedia, atau
foreign key gagal diverifikasi. Untuk MySQL/PostgreSQL, gunakan proses ETL
terpisah yang mengikuti mapping yang sama; skrip ini sengaja dibatasi ke
SQLite agar tidak ada asumsi koneksi production.

- `pages` → `cms_pages`;
- `product_categories` → `product_groups`;
- `client_balance_logs` → `credits`;
- pivot `coupon_product_category` → `coupon_product_group`;
- status order lama → lifecycle canonical (`draft`, `pending_payment`, `paid`,
  `provisioning`, `completed`, `failed`, `cancelled`, atau `expired`);
- status dan field provisioning lama → kolom tracking final;
- invoice/payment lama → kolom idempotency dan status final tanpa menggandakan
  ledger.

Untuk setiap tabel, simpan jumlah sumber, jumlah tujuan, jumlah yang ditolak,
dan alasan penolakan. Jangan menganggap data cleanup historis otomatis sudah
terwakili oleh schema baru.

## 4. Validasi sebelum switch traffic

- [ ] Foreign key dan unique index berhasil dibuat.
- [ ] Jumlah client, invoice, payment, order, domain, hosting account, credit,
      dan dokumen sesuai laporan ETL.
- [ ] Tidak ada payment dengan external transaction yang duplikat.
- [ ] Tidak ada dua coupon reservation untuk invoice yang sama.
- [ ] Saldo client sama dengan hasil ledger.
- [ ] Semua dokumen dan bukti pembayaran dapat dibaca melalui disk private.
- [ ] Provider domain/VPS tidak dipanggil ulang hanya karena proses cutover.
- [ ] Queue, scheduler, cache, dan storage menunjuk ke environment baru.
- [ ] Smoke test berhasil dengan akun non-production.

## 5. Switch dan rollback

- [ ] Hentikan worker lama sebelum switch agar tidak memproses invoice yang
      sama dari dua environment.
- [ ] Aktifkan maintenance singkat, lakukan final delta sync, lalu switch
      traffic ke environment baru.
- [ ] Jalankan satu pemeriksaan read-only setelah switch.
- [ ] Pantau log aplikasi, queue failure, webhook payment, dan provisioning.
- [ ] Jika cutover gagal, kembalikan traffic ke environment lama dan restore
      backup sesuai runbook; jangan mencoba menjalankan `down()` massal di
      production sebagai rollback.

## Batasan penting

Database existing tidak dapat langsung diperlakukan sebagai database baru
hanya dengan mengganti ZIP. Cutover data membutuhkan backup, ETL, validasi,
dan rencana rollback terpisah.