<?php

/*
|--------------------------------------------------------------------------
| Daftar VPS Provider (untuk server bertipe "VM / VPS")
|--------------------------------------------------------------------------
|
| SATU-SATUNYA tempat mendaftarkan tampilan form provider di
| Admin » Infrastruktur » Server. Form Tambah/Edit Server membaca file
| ini untuk: isi dropdown "VPS Provider", kotak panduan, dan field mana
| yang tampil beserta labelnya.
|
| Menambah provider baru (mis. Vultr / Linode / Hetzner):
|   1. Tambah entri di sini.
|   2. Buat adapter yang mengimplementasikan VpsProviderInterface
|      (lihat app/Services/Vps/DigitalOceanVpsProvider.php sebagai contoh).
|   3. Daftarkan adapter-nya di VpsProviderFactory::make().
|
| Kolom tabel `servers` dipakai ulang per provider (tanpa kolom baru):
|   hostname     -> mis. slug lokasi           (opsional, tergantung provider)
|   api_username -> mis. billing account id    (opsional, tergantung provider)
|   api_token    -> API key / Personal Access Token (dienkripsi otomatis)
|
| fields: hanya field yang ADA di sini yang ditampilkan. Field yang tidak
| disebut (hostname / api_username) disembunyikan & dikosongkan.
| Port & nameserver tidak pernah tampil untuk VPS provider.
|
| tone      : warna kotak panduan (primary | info | success | warning)
| cost_sync : true kalau adapter provider mengimplementasikan pricing()
|             ("Tarik Harga Modal" di halaman Server).
| currency  : mata uang harga modal yang dikembalikan provider. Selain IDR,
|             admin WAJIB mengisi kurs ke Rupiah di halaman Server (kolom
|             cost_fx_rate) -- sistem tidak menebak kurs.
| cost_model: 'component' = harga modal per komponen (vCPU/RAM/disk/...),
|             spek VM bebas diatur di produk (contoh: IDCloudHost).
|             'size'      = harga modal per paket/size tetap milik provider,
|             spek VM mengikuti size yang dipilih (contoh: DigitalOcean).
| product_fields: isian tambahan di form Produk (kategori VPS) yang ikut
|             tersimpan di panel_package dan dibaca adapter saat membuat VM.
|             Nama input di form = "vm_" + kunci. source: sizes|regions
|             mengisi saran (datalist) dari hasil "Tarik Harga Modal".
*/

return [

    'idcloudhost' => [
        'label' => 'IDCloudHost',
        'tone'  => 'success',
        'hint'  => '<b>IDCloudHost</b> menyediakan VM/VPS baru langsung lewat API, bukan panel di server yang sudah ada. '
            . 'Ambil API Key dari <a href="https://app.idcloudhost.com" target="_blank" class="text-decoration-underline" style="color:inherit">app.idcloudhost.com</a> » API Token, '
            . 'tempel di kolom <b>API Token</b>. Kolom <b>Slug Lokasi</b> opsional — mis. <code>jkt01</code>, <code>jkt02</code>, <code>jkt03</code>, <code>sgp01</code>; '
            . 'kosongkan untuk lokasi default akun. Kolom <b>Billing Account ID</b> berupa <b>ANGKA</b> '
            . '(mis. <code>1200206137</code> — lihat di halaman Diagnosa); kosongkan untuk memakai akun billing default. Isian selain angka diabaikan.',
        'fields' => [
            'hostname' => [
                'label'       => 'Slug Lokasi (opsional)',
                'placeholder' => 'jkt01 (kosongkan utk default)',
                'required'    => false,
            ],
            'api_username' => [
                'label'       => 'Billing Account ID — angka saja (opsional)',
                'placeholder' => 'mis. 1200206137 — kosongkan utk default',
                'required'    => false,
            ],
            'api_token' => [
                'label' => 'API Token',
            ],
        ],
        'cost_sync'      => true,
        'currency'       => 'IDR',
        'cost_model'     => 'component',
        'product_fields' => [],
    ],

    'digitalocean' => [
        'label' => 'DigitalOcean',
        'tone'  => 'info',
        'hint'  => '<b>DigitalOcean</b> memakai API global — tidak per-server seperti panel hosting biasa, jadi tidak perlu Hostname, Port, atau Nameserver. '
            . 'Ambil <b>Personal Access Token</b> (izin read + write) dari '
            . '<a href="https://cloud.digitalocean.com/account/api/tokens" target="_blank" class="text-decoration-underline" style="color:inherit">cloud.digitalocean.com » API</a>, '
            . 'tempel di kolom <b>API Token</b>.',
        'fields' => [
            'api_token' => [
                'label' => 'API Token (Personal Access Token)',
            ],
        ],
        'cost_sync'  => true,
        'currency'   => 'USD',
        'cost_model' => 'size',
        'product_fields' => [
            'provider_size' => [
                'label' => 'Size (Droplet)', 'placeholder' => 's-1vcpu-1gb', 'required' => true, 'source' => 'sizes',
            ],
            'provider_image_id' => [
                'label' => 'Image OS', 'placeholder' => 'ubuntu-22-04-x64', 'required' => true, 'default' => 'ubuntu-22-04-x64',
            ],
            'location' => [
                'label' => 'Region', 'placeholder' => 'sgp1', 'required' => true, 'default' => 'sgp1', 'source' => 'regions',
            ],
        ],
    ],

    // 'vultr' => [ ... ],
    // 'linode' => [ ... ],
    // 'hetzner' => [ ... ],

];
