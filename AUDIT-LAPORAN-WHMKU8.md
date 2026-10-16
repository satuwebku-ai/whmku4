# Audit & pembersihan kode mati — whmku8

Hasil akhir: **398 test lolos (2064 assertion)**, sebelumnya suite tidak bisa jalan (error fatal). Route tetap 653. Migrasi: 77 file, semuanya `create_*`.

## Pasang
Salin isi folder ini ke root proyek (timpa). Tidak ada migrasi. Lalu `php artisan optimize:clear`.
Jalankan test dengan `.env.testing` berisi `APP_KEY=...` saja; jangan salin `.env.example` apa adanya (domain contohnya bentrok dengan phpunit.xml).

## Keamanan / regresi
- `AdminSeeder`: commit 64bc7e8 mengembalikan versi lama (admin@example.com / Admin12345!, menimpa password tiap seed). Dipulihkan dari commit 978b0fe (kredensial dari ADMIN_EMAIL/ADMIN_PASSWORD, tidak menimpa akun ada). Jika versi lama pernah dijalankan di produksi, ganti password admin. Commit perbaikan ini ke git.
- `ServerProductSeeder` hilang dari working tree (ada di HEAD, dipakai 2 test & docs). Dipulihkan.

## Kode mati dihapus
- `ConsoleController::index()` (duplikat `indexBootstrap()` yang dirutekan)
- `CronJob::getIntervalLabelAttribute()` (tidak dipakai PHP/Blade)
- 3 `use` tak terpakai: CartNotice, InboundMailProcessor, ChatMailMirror

## Perbaikan test (aplikasi sudah benar)
- DuitkuAndRefundAuditTest: helper `callback()` bentrok dengan method final PHPUnit -> `duitkuPayload()`
- ServerGroupProvisioningFlowTest: tidak lagi mengirim `port=NULL` untuk VPS (kolom NOT NULL; controller memang tidak menulisnya)
- CartBadgeTest: cookie dites terenkripsi seperti di produksi
- StoreShellTest: tidak mencocokkan CSS inline
- StoreLayoutSelectionTest: pasang ulang ThemeServiceProvider setelah Setting diubah
- RequirePortalOriginTest: sesuai perilaku `?cart_notice=` (docs/area-boundaries.md)

## Perubahan aplikasi lain
- `TutorialController`: pencarian LIKE memakai `ESCAPE '!'` agar benar di MySQL dan SQLite. **Uji di MySQL.**

## Bersih (diperiksa)
0 route rusak dari 653 · 0 view yatim dari 369 · key config proyek semua terbaca · dependensi Composer & aset public semua dipakai · tidak ada dd/dump/console.log · kode dikomentari hanya 1 baris.

## Tahap 2: kode tak terpakai dihapus (atas permintaan)
- 22 wrapper API provider: DnamaService (13), IdCloudHostService (7), LiquidService (2)
- 14 relasi Eloquent tanpa pemakai (mis. Client::addresses, Invoice::taxRule)
- Sisa berantai: 2 helper DnamaService (getDnssecAlgorithms, getDnssecDigestTypes) dan import HasOne di Invoice
- Verifikasi: 390 test lolos, 653 route, 383 file app/ semua bisa di-load, analyzer ulang tidak menemukan sisa selain di bawah.
- Hanya mempengaruhi 29 file; tabel dan data database TIDAK disentuh.

## Tahap 3
- Dihapus: `app/Models/ClientAddress.php` (hanya dipakai relasi yang dihapus; HAPUS MANUAL, lihat HAPUS-FILE.txt). Tabel `client_addresses` dan migrasinya TIDAK diubah.
- Dihapus: `DocumentRequirementPolicy::viewAny` (tidak ada authorizeResource/pemanggil). Catatan: `manage` di policy yang sama juga tak punya pemanggil eksplisit; dibiarkan.
- Analyzer ulang: 0 method tak terpakai, 0 import tak terpakai. 390 test lolos, 653 route.

## Tahap 4: migrasi digabung + seeder produk
**Migrasi.** Satu-satunya migrasi non-create (`000075_add_service_reference_to_invoice_items_table`) dilebur ke induknya `000026_create_invoice_items_table` (kolom `service_type`, `service_id`, `item_type` + indeks `invoice_items_service_index`). Untuk database yang sudah berjalan:
- sudah menjalankan 000075 -> catatannya dibuang dari tabel `migrations` (peta `migration-renames.php`), kolom sudah ada.
- belum menjalankan -> `SchemaRepair` menambah kolom + indeks dan menjalankan backfill invoice lama (kegagalan backfill hanya dicatat di log; ulangi dengan `php artisan lumora:backfill-invoice-services`).
- **WAJIB di database lama: jalankan `php artisan lumora:sync-migrations` SEBELUM `php artisan migrate`.** Tanpa itu, kolom baru tidak ada dan fitur yang memakai `service_type` akan error.
Diuji dengan membangun database dari migrasi ASLI lalu upgrade ke kode baru (skenario sudah/belum menjalankan 000075): keduanya 3/3 kolom + indeks, 0 pending. `Schema::table` di dalam file create lain sengaja dibiarkan (FK silang antar tabel yang dibuat belakangan).

**Seeder produk** `database/seeders/ProductCatalogSeeder.php` (manual, TIDAK ikut `db:seed` biasa):
`php artisan db:seed --class=ProductCatalogSeeder`
7 kategori berurutan: Basic Shared Hosting, Premium Shared Hosting, Budget VPS, KVM NVMe VPS, Dedicated Server, Remote Desktop, Radio Shoutcast — masing-masing 1 paket awal (Basic 1, Premium 1, Budget VPS 1, KVM NVMe 1, Dedicated Entry, RDP Basic, Shoutcast 50). Shared/Dedicated/Shoutcast = jenis `hosting`; VPS/Remote Desktop = jenis `vps`. Semua ditagih invoice (billing_mode=invoice).
- Semua NONAKTIF; harga & spek hanya CONTOH. Atur di Admin » Produk lalu aktifkan kategori DAN produknya.
- Tidak membuat server/grup server palsu; tanpa server, order jatuh ke aktivasi manual (status `manual`). Untuk otomatis, pasang Grup Server/Server asli; paket WHM `basic` dan `premium` harus dibuat dengan nama sama di WHM.
- Idempoten (firstOrCreate), tidak menimpa perubahan admin, aman berdampingan dengan ServerProductSeeder.
- Dikunci 7 test (termasuk "tidak ada produk aktif" dan "tidak ada server dibuat").

## Opsional (belum dikerjakan)
- Dokumen usang: AUDIT_REPORT.md (menyebut whmku4; berisi langkah upgrade sync-migrations), audit/, README-PATCH*.md
- Tabel `client_addresses` kini tanpa model; buang lewat migrasi baru bila memang tidak akan dipakai
- Seeder: untuk menyertakan katalog ke instalasi baru, tambahkan `ProductCatalogSeeder::class` ke `DatabaseSeeder` setelah harga disesuaikan

## Batasan
Analisis berbasis referensi teks + menjalankan app/test di SQLite; pemanggilan dinamis bisa lolos (condong ke "pertahankan"). MySQL dan integrasi eksternal tidak diuji.
