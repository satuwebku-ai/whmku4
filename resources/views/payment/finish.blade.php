<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Pembayaran — {{ config('app.name', 'Lumora Hosting') }}</title>
<style>html{visibility:hidden}</style>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4" onload="document.documentElement.style.visibility='visible'"></script>
<script>setTimeout(function(){document.documentElement.style.visibility='visible'},2500)</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">

  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-md w-full text-center">
    @if (! $payment)
      <div class="w-14 h-14 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4 text-2xl">?</div>
      <h1 class="text-lg font-bold text-slate-800 mb-2">Pembayaran Tidak Ditemukan</h1>
      <p class="text-sm text-slate-500">Referensi pembayaran tidak dikenali. Silakan hubungi tim support kami.</p>
    @elseif ($payment->status === 'paid')
      <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4 text-2xl">&check;</div>
      <h1 class="text-lg font-bold text-slate-800 mb-2">Pembayaran Berhasil</h1>
      <p class="text-sm text-slate-500">Terima kasih! Pembayaran untuk <b>{{ $payment->reference }}</b> sudah kami terima dan layanan Anda sedang diproses.</p>
    @else
      <div class="w-14 h-14 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4 text-2xl">!</div>
      <h1 class="text-lg font-bold text-slate-800 mb-2">Menunggu Konfirmasi</h1>
      <p class="text-sm text-slate-500">
        Pembayaran <b>{{ $payment->reference }}</b> belum terkonfirmasi lunas.
        Kalau Anda sudah membayar, status akan diperbarui otomatis dalam beberapa menit.
      </p>
    @endif

    @if ($payment)
      <div class="border-t border-slate-100 mt-6 pt-4 text-sm">
        <div class="flex justify-between text-slate-500"><span>Total</span><span class="text-slate-800 font-semibold">Rp {{ number_format($payment->total, 0, ',', '.') }}</span></div>
      </div>
    @endif
  </div>

</body>
</html>
