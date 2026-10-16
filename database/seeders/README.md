# Database seeders

Seeder yang disediakan:

- `RoleSeeder`: membuat role RBAC dan permission per modul.
- `AdminSeeder`: membuat akun superadmin pertama jika email belum terdaftar.
- `ProductCatalogSeeder`: membuat jenis produk, kategori, 5 contoh paket hosting, dan 3 contoh paket VPS serta baris harga siklus.

## Jalankan

```bash
# Pastikan .env dan koneksi database sudah benar, lalu:
php artisan migrate --seed

# Atau setelah migrasi sudah dijalankan:
php artisan db:seed

# Jalankan hanya katalog produk:
php artisan db:seed --class=ProductCatalogSeeder
```

Atur `SEED_ADMIN_NAME`, `SEED_ADMIN_USERNAME`, `SEED_ADMIN_EMAIL`, dan `SEED_ADMIN_PASSWORD` di `.env`. Untuk production, `SEED_ADMIN_PASSWORD` wajib diisi. Pada instalasi lokal tanpa variabel tersebut, akun contoh adalah `admin@example.com` dengan username `admin` dan password `ChangeMe-Immediately-2026!`; segera ganti/hapus akun contoh sebelum aplikasi dibuka ke publik.

Seeder katalog memakai slug tetap sebagai identitas dan aman dijalankan ulang, tetapi data produk contoh ditujukan sebagai titik awal. Sesuaikan harga, spesifikasi, dan nama paket sebelum toko dipublikasikan. Seeder tidak membuat server cPanel/WHM atau kredensial provider; masukkan server nyata secara manual dari panel admin dan jangan menaruh token server di seeder.
