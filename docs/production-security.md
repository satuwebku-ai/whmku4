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