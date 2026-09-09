@include('errors.minimal', [
    'code' => 429,
    'icon' => 'fa-hourglass-half',
    'color' => 'amber',
    'title' => 'Terlalu Banyak Permintaan',
    'message' => 'Anda mencoba terlalu sering dalam waktu singkat. Tunggu sebentar, lalu coba lagi.',
])
