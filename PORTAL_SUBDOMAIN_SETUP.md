# Pengaturan subdomain portal Satu Cloud Hosting

Perubahan kode melayani area admin dan client pada host terpisah:

- Situs publik: `https://satucloudhosting.com/`
- Login admin: `https://pengelola.satucloudhosting.com/login`
- Login client: `https://member.satucloudhosting.com/login`

URL lama `https://satucloudhosting.com/admin/login` kembali ke beranda. URL lama `/client/...` diarahkan ke halaman yang sama di subdomain `member`. Tautan lama `/admin/...` selain halaman login diarahkan ke host `pengelola`.

## Pengaturan yang perlu dilakukan di penyedia domain/hosting

1. Buat record DNS `A`/`AAAA` atau `CNAME` untuk `pengelola` dan `member`, arahkan ke server atau tujuan hosting yang sama dengan situs utama. Gunakan nilai tujuan yang diberikan penyedia hosting; jangan menebak alamat IP.
2. Atur hosting/web server agar kedua hostname membuka aplikasi Laravel yang sama dan document root-nya menunjuk ke folder `public` aplikasi.
3. Pasang sertifikat HTTPS yang mencakup `satucloudhosting.com`, `pengelola.satucloudhosting.com`, dan `member.satucloudhosting.com`. Aplikasi mengarahkan HTTP ke HTTPS.
4. Di `.env` produksi, gunakan:

   ```dotenv
   APP_URL=https://satucloudhosting.com
   PUBLIC_DOMAIN=satucloudhosting.com
   ADMIN_DOMAIN=pengelola.satucloudhosting.com
   CLIENT_DOMAIN=member.satucloudhosting.com
   SESSION_DOMAIN=null
   SESSION_SECURE_COOKIE=true
   GOOGLE_REDIRECT_URI=https://member.satucloudhosting.com/auth/google/callback
   ```

   Pertahankan `SESSION_DOMAIN=null` agar cookie login admin dan client hanya berlaku pada host masing-masing.
5. Jika login Google aktif, tambahkan URL callback di atas ke daftar **Authorized redirect URIs** pada Google Cloud Console.
6. Setelah perubahan `.env`, bersihkan dan bangun ulang cache Laravel:

   ```bash
   php artisan optimize:clear
   php artisan route:cache
   php artisan config:cache
   ```

## Pengecekan

- Buka `https://satucloudhosting.com/admin/login`; browser harus kembali ke beranda situs utama.
- Buka `https://pengelola.satucloudhosting.com/`; tamu diarahkan ke login admin.
- Buka `https://member.satucloudhosting.com/`; tamu diarahkan ke login client.
- Pastikan dashboard, login, lupa password, verifikasi email, dan callback Google tetap memakai subdomain portal yang sesuai.
