<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun admin awal (dipakai AdminSeeder)
    |--------------------------------------------------------------------------
    | Isi lewat .env supaya tidak ada kredensial di kode sumber. Kalau
    | ADMIN_PASSWORD kosong, seeder membuat password acak dan menampilkannya
    | SEKALI di layar.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
