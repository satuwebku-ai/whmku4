# Patch: rapikan sidebar + menu CMS/Pengaturan

Salin ke root proyek (timpa), lalu: php artisan optimize:clear
Tidak ada migrasi. Nama route tidak ada yang berubah.

- layouts/admin.blade.php : grup "Konten" -> "Situs Publik" (8 menu), Sistem + "Kirim Promo", Pengaturan -> hub
                            fix: Aktivitas ikut menyala di Banner Promo (pola admin.promo*), Blog/Pusat Bantuan aktif ganda
- admin/pages/_nav        : tab CMS dikelompokkan + Halaman Depan; tab tetap menyala di halaman tambah/ubah
- admin/settings/_nav     : tab Pengaturan dikelompokkan (Situs/Bisnis/Komunikasi/Sistem); Halaman Depan dipindah ke CMS
- admin/settings/index    : hub Pengaturan per kelompok (sekarang bisa dibuka dari sidebar)
- routes/admin.php        : settings/homepage pindah dari module:system ke module:content
- Models/NavMenu.php      : tujuan menu publik + Blog & Pusat Bantuan
- SettingController.php   : data hub per kelompok
