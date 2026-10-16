# Pengaturan keamanan produksi

Sebelum membuka Lumora ke publik, atur nilai berikut di `.env` server:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

- `SESSION_SECURE_COOKIE=true` hanya berfungsi benar jika situs memakai HTTPS.
- Aplikasi sudah membaca `TRUSTED_PROXIES`. Isi dengan IP/CIDR reverse proxy yang Anda kendalikan, atau biarkan kosong bila server menerima koneksi langsung. Jangan memakai `*`.
- Jalankan `php artisan config:cache` setelah perubahan konfigurasi.
- Antrian email/notifikasi eksternal perlu worker Laravel yang selalu berjalan. Feed notifikasi dalam panel admin diperbarui sendiri setiap 15 detik dan tidak bergantung pada worker tersebut.
- CSP masih memakai `Content-Security-Policy-Report-Only`. Tinjau entri `CSP violation report` di log terlebih dahulu; jangan mengubahnya menjadi kebijakan pemblokiran sampai sumber yang sah sudah diizinkan.
- Backup baru ditandatangani dengan HMAC berbasis `APP_KEY`; tanda tangan diverifikasi sebelum database atau file dipulihkan. Pertahankan `APP_KEY` yang sama selama backup bertanda tangan masih perlu dipulihkan. Rotasi `APP_KEY` membuat tanda tangan lama tidak dapat diverifikasi. Backup lama tanpa tanda tangan hanya bisa dipulihkan setelah konfirmasi eksplisit, dan keasliannya tetap tidak terjamin.
- Setelah perubahan diterapkan, jalankan `composer audit` di lingkungan proyek yang dapat mengakses Packagist dan tinjau advisori sebelum rilis.
## Memulihkan backup dari versi lama

Struktur migrasi pernah dirapikan (kolom tambahan dilebur ke migrasi tabel induknya). Backup yang dibuat **sebelum** itu berisi skema dan tabel `migrations` versi lama. Memulihkannya sekarang aman:

- `php artisan lumora:restore` otomatis menjalankan `lumora:sync-migrations` setelah database dipulihkan. Perintah itu (1) menyelaraskan nama file di tabel `migrations` dan (2) melengkapi kolom/tabel yang hilang lewat `App\Support\SchemaRepair` (tidak pernah menghapus data; hanya menambah atau melonggarkan tipe kolom).
- Setelah restore lewat cara lain (impor SQL manual), jalankan sendiri **sebelum** `php artisan migrate`:

  ```
  php artisan lumora:sync-migrations --dry-run   # lihat apa yang akan diperbaiki
  php artisan lumora:sync-migrations
  php artisan migrate
  ```

- Aman diulang; pada database yang sudah lengkap hasilnya "skema sudah lengkap". Opsi `--no-repair` melewati langkah pelengkapan skema.
- Perbaikan yang butuh MySQL (enum menjadi string pada `chat_conversations.channel` dan `credits.type`) hanya terdeteksi di MySQL, bukan di SQLite.

## Serah-terima publik -> client: catatan keamanan

Form di halaman publik (katalog, lisensi, cek domain, premium, transfer) mengirim POST langsung ke keranjang di domain client (lihat `docs/area-boundaries.md`). Karena sesi dan token CSRF kedua domain terpisah, enam route itu dikecualikan dari CSRF dan dijaga:

- **Asal permintaan** (`RequirePortalOrigin`): hanya `Origin` (atau `Referer` bila `Origin` tidak ada) dari domain publik atau client persis. Pemeriksaan ini melindungi dari situs lain yang memakai browser korban. Klien non-browser (curl) bisa memalsukan header, tetapi dampaknya hanya mengisi keranjang sesi mereka sendiri; tidak bisa menyentuh keranjang atau akun orang lain.
- **Pembatas laju** (`cart-handoff`): 30 permintaan per menit per IP.
- **Pesan galat yang kembali ke halaman publik** (`CartNotice`): terenkripsi, kedaluwarsa 2 menit, terikat ke IP pengunjung yang membuatnya, hanya membawa isian form yang dikenal dan tidak pernah membawa kode transfer. Pengikatan IP mencegah penyerang yang memalsukan `Origin` membuat tautan berisi teks palsu untuk korban.
- **Cookie `cart_count`**: hanya angka (0-99), dienkripsi, ditulis di domain induk agar lencana keranjang tampil di halaman publik. Dibaca sebagai bilangan bulat yang dibatasi.

**Wajib untuk server di belakang CDN/load balancer:** isi `TRUSTED_PROXIES` dengan IP proxy yang sebenarnya (bukan `*`). Tanpa itu `$request->ip()` adalah IP proxy, sehingga semua pengunjung berbagi satu jatah 30 permintaan per menit (pengunjung sah ikut terblokir) dan pengikatan IP pada `CartNotice` tidak efektif. Dengan `*` penyerang bisa memalsukan `X-Forwarded-For` untuk melewati pembatas.

**Seeder contoh:** `ServerProductSeeder` membuat server NONAKTIF dengan hostname `.invalid`. Saat order hosting diproses, panel dihubungi dengan nama pengguna, kata sandi akun, dan domain pelanggan; jangan pernah mengaktifkan server dengan hostname yang bukan milik Anda.

**Diketahui dan diterima:** kebijakan CSP `form-action 'self' https:` bersifat longgar (komentar di `CspPolicy` menjelaskan alasannya: Chrome menerapkannya juga pada redirect gateway pembayaran); pengetatan perlu diuji terhadap semua gateway.
