# CMS, Blog, dan Pusat Bantuan

Halaman statis, artikel blog, dan kategori blog memakai tabel bersama `cms_contents`. Kolom `type` membedakan `page`, `post`, dan `category`; model `CmsPage`, `CmsPost`, serta `CmsCategory` tetap tersedia dan otomatis membatasi query ke tipenya sendiri.

Tabel `cms_contents` dibuat langsung oleh migration `2026_01_01_000016_create_cms_contents_table.php`; tidak ada lagi tabel `cms_pages`, `cms_posts`, atau `cms_categories`. `nav_menus.page_id` menunjuk ke `cms_contents` (tipe `page`). Database lama cukup menjalankan `php artisan lumora:sync-migrations` sebelum `php artisan migrate` agar catatan migrasinya selaras.

Skrip `legacy-baseline-etl.php` juga menerima source lama yang masih menyimpan tiga tabel terpisah, atau source baru yang sudah memakai `cms_contents`. Mode dry-run tetap tersedia sebelum `--apply`.

Panel admin menyediakan satu pintu masuk CMS melalui menu **CMS → Pengelolaan CMS**. Dashboard mengelompokkan pengaturan beranda, halaman, navigasi, promosi, artikel, dan template notifikasi. Knowledge Base tetap memakai tabel terpisah karena skema dan fiturnya berbeda, tetapi dikelola dari dashboard CMS yang sama.

## Panel admin

Semua halaman berada di modul `content` dan mengikuti middleware admin yang sudah dipakai aplikasi.

- `admin.blog.index`: buat/edit/hapus artikel, kategori, status draf/terbit, tanggal publikasi terjadwal.
- `admin.blog.categories`: kategori artikel blog.
- `admin.knowledge-base.index`: buat/edit/hapus panduan, kategori, status terbit, penghitung tampilan.
- `admin.knowledge-base.categories`: kategori Pusat Bantuan.

Slug dibuat dari judul/nama jika kosong dan ditambahkan akhiran angka saat terjadi duplikat. Konten HTML dibersihkan dengan `HtmlSanitizer` saat dirender.

## Halaman publik

- `/blog` dan `/blog/{slug}` menampilkan artikel yang sudah terbit.
- `/knowledge-base` dan `/knowledge-base/{slug}` menampilkan panduan yang sudah terbit.
- Kedua halaman utama menyediakan filter kategori dan pencarian judul.
- Tautan Blog dan Pusat Bantuan ditambahkan ke footer tiap tema publik.

## Pemeriksaan

Test feature mencakup isolasi tipe konten pada tabel bersama, visibilitas draf, sanitasi HTML, dan penghitung tampilan. Jalankan di checkout aplikasi lengkap dengan:

```sh
php artisan test --filter='CmsContentStorageTest|PublicEditorialContentTest'
```
