<?php

/**
 * Daftar tema yang tersedia untuk area Publik dan Client.
 *
 * Setiap key di sini HARUS punya folder yang cocok di
 * resources/views/themes/{key}/public atau .../{key}/client.
 *
 * Untuk menambah tema baru:
 * 1. Duplikat resources/views/themes/default/{public|client} ke
 *    resources/views/themes/{key-tema-baru}/{public|client}, lalu ubah
 *    tampilannya sesuka hati (boleh cuma override sebagian file saja --
 *    file yang tidak di-override otomatis jatuh balik ke tema "default").
 * 2. Daftarkan key + label-nya di bawah ini supaya muncul sebagai
 *    pilihan di Admin > Pengaturan > Umum.
 */

return [

    'public' => [
        'default' => [
            'label' => 'Default (Lumora)',
        ],
    ],

    'client' => [
        'default' => [
            'label' => 'Default (Lumora)',
        ],
    ],

];
