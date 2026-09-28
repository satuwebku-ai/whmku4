<?php

/**
 * Daftar tema yang tersedia untuk area Publik dan Client.
 *
 * Setiap key di sini HARUS punya folder yang cocok:
 *   - Publik : resources/views/themes/public-themes/{key}/public/...
 *   - Client : resources/views/themes/client-themes/{key}/client/...
 *
 * Publik dan Client SENGAJA dua pohon folder terpisah total (bukan
 * satu folder tema berisi public/ + client/ sekaligus) -- supaya
 * memilih tema tertentu untuk Publik tidak pernah ikut mengubah
 * tampilan Client meskipun key-nya kebetulan sama, begitu juga
 * sebaliknya. Lihat catatan lengkap di app/Providers/ThemeServiceProvider.php.
 *
 * Untuk menambah tema baru:
 * 1. Duplikat resources/views/themes/public-themes/default ke
 *    resources/views/themes/public-themes/{key-tema-baru}, dan/atau
 *    resources/views/themes/client-themes/default ke
 *    resources/views/themes/client-themes/{key-tema-baru}.
 *    Lalu ubah tampilannya sesuka hati (boleh cuma override sebagian
 *    file saja -- file yang tidak di-override otomatis jatuh balik
 *    ke tema "default").
 * 2. Daftarkan key + label-nya di bawah ini (di grup 'public' dan/atau
 *    'client') supaya muncul sebagai pilihan di Admin > Pengaturan >
 *    Umum. Boleh cuma didaftarkan di salah satu grup saja kalau tema
 *    itu memang khusus dibuat untuk satu area.
 */

return [

    'public' => [
        'default' => [
            'label' => 'Default (Lumora)',
        ],
        'modern' => [
            'label' => 'Modern (Elegan)',
        ],
    ],

    'client' => [
        'default' => [
            'label' => 'Default (Lumora)',
        ],
        'modern' => [
            'label' => 'Modern (Elegan)',
        ],
    ],

];
