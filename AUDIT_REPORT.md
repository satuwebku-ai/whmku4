# Laporan Audit Kode — whmku4 (Lumora)

Audit dijalankan dengan **menjalankan** aplikasinya (Laravel + SQLite di sandbox): `route:list`, kompilasi seluruh Blade,
migrasi dari nol, dan test suite. Setiap penghapusan diverifikasi ulang dengan test, bukan hanya pencarian teks.

## Ringkasan

| | Sebelum | Sesudah |
|---|---|---|
| Test | 91 (1 gagal) | **136 lulus** (367 assertion) |
| Route | 551 | 551 (tidak ada yang berubah/hilang) |
| Baris PHP + Blade | 99.113 | 85.791 (**−13.322**) |
| File migrasi | 87 | 76 |
| View gagal kompilasi | 1 | 0 |
| Tabel database | 91 | 89 (2 tabel mati dibuang) |

---

## ⚠️ LANGKAH UPGRADE (baca dulu)

Untuk **database yang sudah berjalan** (bukan instalasi baru), urutannya:

```bash
# 0. BACKUP database dulu.
php artisan lumora:sync-migrations --dry-run      # lihat apa yang akan diubah
php artisan lumora:sync-migrations                # sekali saja; menyelaraskan nama migrasi
php artisan lumora:sync-migrations --drop-unused-tables   # opsional: buang tabel kosong hosting_packages & ticket_departments
php artisan migrate                               # harus menjawab "Nothing to migrate"
php artisan lumora:encrypt-settings               # enkripsi token/secret lama di tabel settings
php artisan optimize:clear
```

Jika `migrate` dijalankan **sebelum** `sync-migrations` pada database lama, ia akan mencoba membuat ulang tabel (error "already exists",
tidak ada data yang hilang). Modal checklist setup juga akan memberi petunjuk ini. Instalasi baru tidak perlu langkah di atas.

**Kredensial admin:** `AdminSeeder` sebelumnya menanam email + password admin di kode sumber. Kini diambil dari `.env`
(`ADMIN_EMAIL`, `ADMIN_PASSWORD`; kosong = password acak ditampilkan sekali). **Jika password lama itu pernah dipakai di produksi atau repo
pernah dibagikan, anggap sudah bocor dan ganti sekarang.**

---

## 1. Bug bawaan yang ditemukan (bukan sekadar sampah)

| Temuan | Dampak | Tindakan |
|---|---|---|
| `admin/affiliate/commissions.blade.php`: `@endif` nyasar sebelum `@elseif`, blok tidak tertutup | Halaman **Admin → Affiliate → Komisi error 500** | Diperbaiki + smoke test |
| Test `ClientBillingPortalPhase18Test` mencari view di lokasi lama (sebelum sistem tema) | 1 test selalu gagal | Path diperbaiki |
| `Setting::putMany()` tidak pernah menyetel `is_encrypted` | Kunci yang dulu terenkripsi lalu ditimpa lewat `putMany` terbaca `null` | Diperbaiki + test |
| Nama migrasi bertanggal 2027–2029 (lebih maju dari sekarang) | `make:migration` baru (2026) akan berurutan **sebelum** tabel induknya dibuat | Semua dinomori ulang ke 2026 |
| `domains.tld_premium_id` FK ke `tld_premiums` yang dibuat **setelah** `domains` | SQLite diam saja, **MySQL akan gagal** membuat FK | Urutan diperbaiki |
| `routes/api.php` tidak pernah dimuat (`bootstrap/app.php` tanpa `api:`) | Endpoint availability & webhook di file itu tidak pernah aktif | File + controller-nya dihapus (webhook aktif ada di `web.php`) |

## 2. Duplikat

- **8 controller auth ganda** (`Admin/Auth/{Login,Otp}`, `Client/Auth/{Login,Otp,Register,ForgotPassword,GoogleAuth,VerifyEmail}`) — tidak dirutekan sama sekali; salinan dari `Auth/Admin/*` dan `Auth/Client/*`. 4 di antaranya identik byte-per-byte. Dihapus.
- **94 file view tema** (`modern`, `namahost`) yang identik byte-per-byte dengan tema `default`. Sistem tema sudah fallback ke `default`, jadi dihapus (tampilan tidak berubah).
- `welcome.blade.php` bawaan Laravel: memakai route `login`/`register` yang tidak ada, tidak dipakai route mana pun. Dihapus.

## 3. Kode mati yang dihapus

- **144 method controller** tanpa route dan tanpa pemanggil internal (sisa migrasi ke versi `*Bootstrap`). Daftar: `audit/REMOVED_METHODS.txt`.
- 20 method lain yang terverifikasi tak dipanggil (mis. `Tld::costForYears`, `TaxService::forCountry`, `LiquidService::pickPrice`).
- Class tanpa referensi: `ExpireTrials` (fitur trial sudah dihapus — cron-nya dibuang migrasi), enum `InvoiceStatus`/`PaymentStatus`,
  `InvoiceOverdueException`, 4 FormRequest (`DomainSearchRequest`, `CheckoutRequest`, `PaymentRequest`, `RequirementUploadRequest`),
  `ImageFitter`, model `HostingPackage`, `TicketDepartment`, `ProductCategory` (shim lama).
- 19 `use` yang tidak terpakai di 18 file.

## 4. Migrations

- 8 migrasi "alter" **dilebur** ke migrasi `create` tabelnya (addons, admins, nav_menus, domains, orders, invoices, payments).
- Dibuang: migrasi tabel mati (`hosting_packages`, `ticket_departments`) dan data-fix fitur trial.
- Semua dinomori `2026_01_01_0000NN_…` — unik, berurutan, dan tidak lagi berbenturan dengan timestamp `make:migration` berikutnya.
- **Bukti:** skema hasil migrasi baru dibandingkan kolom-per-kolom, index, dan FK dengan skema lama → **89 tabel, 0 perbedaan**.
  Database lama yang di-upgrade lewat `sync-migrations` menghasilkan skema identik dengan instalasi baru. `migrate:rollback` bersih.
- Pemetaan nama lama→baru: `database/migration-renames.php`.

## 5. Keamanan

| Sev. | Temuan | Status |
|---|---|---|
| **Tinggi** | Email + password superadmin tertanam di `AdminSeeder`; `updateOrCreate` juga **menimpa** password tiap seed | ✅ Dari `.env`/acak; tidak menimpa akun yang ada |
| **Tinggi** | Stored XSS: konten Pengumuman & Halaman CMS ditampilkan `{!! !!}` tanpa sanitasi | ✅ `HtmlSanitizer` (allowlist), 18 vektor serangan diuji |
| Sedang | SSRF: `supplier_api_url` dipanggil server tanpa batasan (bisa ke `169.254.169.254`, localhost) | ✅ `UrlGuard` + redirect dimatikan |
| Sedang | Token WhatsApp, secret reCAPTCHA, kunci AI, VAPID privat tersimpan **teks biasa** di DB (ikut ke file backup) | ✅ Dienkripsi; `lumora:encrypt-settings` untuk data lama |
| Sedang | Tidak ada header keamanan (clickjacking, nosniff, HSTS) | ✅ `SecurityHeaders` middleware |
| Rendah | Registrasi klien tanpa pembatas laju | ✅ `throttle:10,10` |

**Sudah baik (tidak perlu diubah):** semua SQL mentah memakai binding · tidak ada mass-assignment liar · tidak ada `env()` di luar config ·
tidak ada sisa `dd()/dump()` · login/OTP/lupa-password punya lockout · restore backup hanya `superadmin` · webhook Duitku diverifikasi `hash_equals` ·
kredensial server/gateway/registrar terenkripsi · upload divalidasi mime+ukuran dan tidak ada file sensitif di disk publik ·
tidak ada open redirect · cek kepemilikan (`authorizeOwner`) ada di setiap jalur data klien.

## 6. Sengaja TIDAK dihapus (dan kenapa)

- **Policy (7) & Listener** — tampak "tanpa referensi" tapi ditemukan otomatis Laravel dan dipakai lewat `Gate::forUser()->authorize()` / `InvoicePaid::dispatch()`.
- **`checkAvailability()` di 4 registrar** — dituntut `DomainRegistrarInterface`. Nyatanya tak ada yang memanggilnya (jalur lain dipakai `AvailabilityService`); kandidat penyederhanaan interface, perlu keputusan Anda.
- **Wrapper API provider** (DNSSEC, child nameserver, floating IP di `DnamaService`/`LiquidService`/`IdCloudHostService`) — kapabilitas API yang belum punya UI.
- **Relasi Eloquent tak terpakai** dan **model `User`** (ada di `config/auth.php`, tapi tabel `users` tidak ada).
- **Command manual** (`adopt-vm`, `demo-order`, `inspect-*`, `migrate-payment-proofs`, dll.) — alat operasional, bukan kode mati.
- View yang dipanggil dinamis (`public.home._*`, `admin.registrars.diagnostics-{provider}`, view yang dilempar sebagai argumen helper).

## 7. Rekomendasi lanjutan (belum dikerjakan — perlu keputusan)

1. **14 model "kerangka"** (skema + model ada, belum ada UI/logic): `ClientAddress`, `ClientGroup`, `CmsPost/Category`, `Currency/CurrencyRate`, `DomainContact`,
   `HostingDomain`, `KnowledgeBase/Category`, `OrderItem`, `OrderStatusLog`, `ProductPricing`, `ServerGroup`. Putuskan: lanjutkan atau buang beserta tabelnya.
2. Model `ProductGroup` memakai tabel `product_categories` (nama tidak selaras).
3. Middleware `PermissionMiddleware` dan alias `2fa` (`TwoFactorMiddleware`) belum dipakai route mana pun.
4. **CSP** belum dipasang (banyak skrip inline/CDN) — perlu rollout bertahap.
5. Produksi: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, set `TRUSTED_PROXIES` bila di belakang proxy.
6. `composer audit` tidak bisa dijalankan di sandbox (packagist diblokir) — jalankan di lingkungan Anda untuk mengecek kerentanan dependensi.
7. ✅ **Selesai:** backup ditandatangani HMAC-SHA256 dan diverifikasi sebelum restore (web + CLI). Backup lama tanpa tanda tangan butuh konfirmasi eksplisit; tanda tangan salah ditolak.
   Kunci: `BACKUP_SIGNING_KEY` (opsional, min. 32 byte; kosong = `APP_KEY`). Saat rotasi, pindahkan kunci lama ke `BACKUP_SIGNING_KEY_PREVIOUS` (pisahkan koma).
   `APP_KEY` tetap diterima sebagai kandidat verifikasi. Simpan kunci ini bersama arsip backup.

## Keterbatasan audit

Sandbox memakai SQLite; perilaku khusus MySQL (mis. `ALTER ... ENUM`) diperiksa lewat pembacaan kode, bukan dijalankan. Integrasi ke layanan
eksternal (WHM, registrar, gateway pembayaran) tidak diuji. Halaman yang tidak punya test hanya dijamin lolos kompilasi Blade dan pengecekan route/view, bukan render penuh.
