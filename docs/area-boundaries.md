# Batas Area Aplikasi

## Public (CMS + etalase toko)

Domain publik melayani konten (`routes/public.php`): beranda, halaman CMS, pengumuman, blog, knowledge base, promo, chat, dan callback pembayaran. Ia juga memuat **etalase toko** (`routes/store.php`) yang hanya MEMBACA: katalog hosting/VPS, halaman kategori & produk, lisensi, cek domain (biasa dan premium), dan formulir transfer domain. URL-nya tidak berubah (`/hosting`, `/vps`, `/cek-domain`, `/domain-premium`, `/lisensi`, ...), jadi tautan lama dan SEO aman. Area ini tidak menangani data layanan milik akun client dan tidak membuat order.

`/keranjang` lama di domain publik diarahkan (301) ke keranjang di portal client.

## Client (portal + keranjang)

Portal client (`member.*`) memuat:

- **Portal** (`routes/client.php`): layanan, domain, tagihan, profil, tiket, dan checkout yang membuat order serta invoice (butuh login).
- **Keranjang & pemesanan** (`routes/cart.php`, prefix `/store`): keranjang dan semua aksi "pilih" dari etalase. Tamu boleh membuat keranjang; login baru diminta saat checkout. Prefix `/store` wajib karena portal client sudah memakai `/vps` dan `/vps/{vps}`.

### Alur "pilih -> keranjang"

Pengunjung menjelajah di domain publik. Saat memilih ("Pesan", "Tambah ke Keranjang", tambah domain, domain premium, lisensi, transfer), form di halaman publik mengirim **POST langsung ke domain client**, lalu pengunjung mendarat di keranjang. Karena sesi dan token CSRF publik berbeda dengan sesi client:

- Route serah-terima (enam route di `routes/cart.php`) dikecualikan dari CSRF lewat `RequirePortalOrigin::HANDOFF_PATHS` (didaftarkan di `bootstrap/app.php`).
- Sebagai gantinya, middleware `RequirePortalOrigin` hanya menerima permintaan yang `Origin` (atau `Referer`)-nya berasal dari domain publik atau client. Situs lain tidak bisa mengisi keranjang pengunjung. Tes `RequirePortalOriginTest` menjaga agar daftar pengecualian selalu sama dengan route yang dijaga.
- Kegagalan dari situs publik (mis. IP lisensi tidak valid) dikembalikan ke halaman publik asal beserta pesan dan isian formnya (kecuali kode transfer). Pesan flash tersimpan di sesi client dan tidak terlihat di halaman publik, jadi pesan dibawa sebagai parameter `?cart_notice=` yang terenkripsi dan kedaluwarsa dalam 2 menit (`CartNotice`, dibuka `RestoreCartNotice` di domain publik lalu URL dibersihkan). Alamat tujuan selalu dibuat server dari route publik, bukan dari `Referer` (kebijakan `strict-origin-when-cross-origin` hanya mengirim origin). Bila halaman asal tidak diketahui, pengunjung dibawa ke keranjang dengan pesan galat (`HandlesStoreHandoff`).
- Tiap IP dibatasi 30 permintaan per menit ke route serah-terima (pembatas bernama `cart-handoff` di `AppServiceProvider`; percobaan dengan asal salah ikut dihitung). Pengunjung yang melewati batas diarahkan ke keranjang dengan pesan "Terlalu banyak permintaan", bukan halaman 429 mentah.
- Kode transfer domain dikirim lewat POST (bukan URL), sehingga tidak masuk log akses.
- Cookie sesi client ikut terkirim pada POST dari domain publik hanya bila kedua domain satu "site" (subdomain dari domain yang sama, seperti `satucloudhosting.com` dan `member.satucloudhosting.com`).

Nama route (`catalog.*`, `cart.*`, `license.*`, `domain.*`) tidak berubah; `route()` otomatis menghasilkan URL di domain yang benar (etalase = publik, keranjang = client).

Tampilan keranjang: halaman meng-extend `public.store-layout` (satu file di `resources/views/themes/public-themes/default/public/store-layout.blade.php`, dipakai semua tema lewat fallback ke `default`). Pembungkus memilih layout induk lewat `ThemeRegistry::storeLayout()`: klien yang sudah login melihat keranjang di dalam portal client (sidebar + menu), tamu memakai layout publik. Bila tema Publik dan Client berbeda, middleware `UseClientThemeForStore` (untuk semua pengunjung di domain client) me-resolve view dari tema Client supaya CSS-nya cocok dengan layout client. View toko menaruh isinya di `@section('store-content')` (atau `@section('full-width')` untuk halaman berlatar penuh), bukan `@section('content')`.

Layout store di portal client: halaman belanja (keranjang, checkout, dan invoice tepat setelah checkout) memakai **mode store** pada layout client semua tema. Halaman mengaktifkannya dengan `@section('store-mode')`; layout lalu menukar topbar + sidebar dashboard dengan header store (`partials/store-topbar`: merek, pintasan Hosting/VPS/Domain/Lisensi, tombol Dashboard, keranjang dengan lencana, akun), sedangkan chrome lain (pita impersonasi, modal konfirmasi, skrip, livechat) tetap sama. Di bawahnya header halaman (`partials/store-hero`: judul, keterangan, dan langkah Keranjang -> Checkout -> Pembayaran lewat `partials/store-steps`). Warna mengikuti `--lumora-theme` tiap tema dan ikon memakai Font Awesome, jadi satu desain berlaku untuk semua tema. View mengatur header lewat `$storeTitle`, `$storeSubtitle`, `$storeStep`. Layout dipilih berdasarkan DOMAIN (`ThemeRegistry::storeLayout()`): di domain client selalu layout client mode store, untuk klien login maupun tamu (tamu melihat tombol Masuk dan Daftar, bukan Dashboard dan akun; logo mengarah ke beranda); di domain publik tetap layout publik. Header store mengikuti tema client aktif: Default memakai gradien indigo gelap + pola titik (seperti `#clientTopbar`-nya), Modern memakai krem translusen + blur (seperti `#clientTopbar`-nya), Namahost memakai navy-teal seperti sidebar-nya dengan aksen cyan (tema ini tidak punya topbar asli), dan tema buatan sendiri memakai varian putih netral dengan aksen `--lumora-theme`. Semua warna header berasal dari variabel `--st-*` yang diisi per varian di `partials/store-topbar`. Jangan menulis direktif php (arobase + php) di dalam komentar Blade: `BladeViewsCompileTest` menjaganya.

Tutorial: `/tutorial` dan `/tutorial/{slug}` di portal client menampilkan panduan Pusat Bantuan (`KnowledgeBase`) yang sudah terbit, dengan pencarian dan filter kategori, tanpa tabel baru. Admin mengelolanya di Konten > Knowledge Base.

### Jenis produk dan perilaku (kind)

Aturan VPS vs hosting dibaca dari `product_types.kind` (data admin) lewat `ProductGroup::isVps()`, scope `ProductGroup::ofKind()/notOfKind()`, dan `Product::vpsType()/hostingType()`. Tidak ada lagi kolom `product_groups.type` (instalasi lama masih memilikinya, tidak dipakai kode). Segmen URL katalog adalah `product_types.slug`; perilakunya tetap ditentukan `kind`, jadi slug bebas (mis. `cloud`) tanpa mengubah aturan. Konstanta perilaku (`ProductType::KIND_VPS`, `KIND_HOSTING`) hanya didefinisikan di `ProductType`.

### Menu client

Menu sidebar client didefinisikan sekali di `App\Support\ClientMenu` dan dipakai ketiga tema client (tema hanya memilih set ikon). Item "Pesan Hosting/VPS/Domain/Lisensi" membuka etalase di domain publik. Lencana jumlah keranjang di header halaman publik dibaca dari cookie `cart_count` (hanya angka) di domain induk yang ditulis `ShareCartCount` di domain client (`CartBadge`); bila domain client bukan subdomain domain publik, lencana di halaman publik tidak ditampilkan.

## Admin

Route admin tetap berada di `routes/admin.php` dan hanya dilayani pada domain admin. Pengelolaan produk, grup/server, klien, order, billing, dan konten tetap menjadi tanggung jawab admin.

## Batas provisioning server

Produk hosting memilih satu sumber penempatan: server langsung atau grup server. Pemilihan otomatis dari grup hanya memakai anggota cPanel yang aktif, tidak maintenance, dan masih berkapasitas. Anggota lama dari panel lain tetap terlihat saat mengedit grup agar admin dapat menghapusnya secara sadar; anggota tersebut tidak dipakai untuk order otomatis. Jika grup tidak memiliki server cPanel yang siap, checkout tetap memakai jalur aktivasi manual.

Produk upgrade dapat menggunakan server langsung yang sama atau grup aktif yang masih mencakup server akun saat ini. Upgrade tidak memindahkan akun ke server lain. Diagnostik server juga menampilkan produk yang terhubung melalui grup beserta nama grup dan kecocokan paketnya.
