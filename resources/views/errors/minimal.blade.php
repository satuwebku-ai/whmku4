<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'Terjadi Kesalahan' }} — {{ config('app.name') }}</title>
  <style>html{visibility:hidden}</style>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4" onload="document.documentElement.style.visibility='visible'"></script>
  <script>setTimeout(function(){document.documentElement.style.visibility='visible'},2500)</script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="antialiased bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center px-6">

  {{--
    SENGAJA tidak pakai layout aplikasi (public.layout / client.layout) dan
    TIDAK memanggil Setting::get() atau query database apa pun di sini.

    Kalau penyebab error ASLI adalah database bermasalah (mis. koneksi
    putus, tabel settings rusak), dan halaman error ini ikut memanggil
    query, itu akan gagal LAGI di tengah proses menampilkan pesan error —
    pengunjung jadi tidak melihat apa-apa sama sekali, alih-alih pesan
    yang jelas. config('app.name') aman karena diambil dari .env, tidak
    pernah menyentuh database.
  --}}

  <div class="max-w-md w-full text-center">
    <div class="w-16 h-16 rounded-2xl bg-{{ $color ?? 'slate' }}-100 text-{{ $color ?? 'slate' }}-600 flex items-center justify-center mx-auto mb-6">
      <i class="fa-solid {{ $icon ?? 'fa-circle-exclamation' }} text-2xl"></i>
    </div>

    <p class="text-6xl font-bold text-slate-200 mb-2">{{ $code ?? '' }}</p>
    <h1 class="text-xl font-bold text-slate-800 mb-2">{{ $title ?? 'Terjadi Kesalahan' }}</h1>
    <p class="text-sm text-slate-500 mb-8 leading-relaxed">{{ $message ?? 'Silakan coba lagi beberapa saat lagi.' }}</p>

    <div class="flex items-center justify-center gap-3">
      <a href="{{ url('/') }}" class="btn btn-primary inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">
        <i class="fa-solid fa-house text-xs"></i> Kembali ke Beranda
      </a>
      <button onclick="history.back()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors">
        <i class="fa-solid fa-arrow-left text-xs"></i> Halaman Sebelumnya
      </button>
    </div>
  </div>

</body>
</html>
