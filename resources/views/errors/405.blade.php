@include('errors.minimal', [
    'code' => 405,
    'icon' => 'fa-ban',
    'color' => 'amber',
    'title' => 'Metode Tidak Diizinkan',
    'message' => 'Halaman ini tidak bisa dibuka langsung lewat alamat di browser — biasanya cuma bisa diakses lewat tombol atau formulir tertentu di aplikasi.',
])
