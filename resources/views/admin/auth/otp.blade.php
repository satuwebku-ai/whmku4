<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Login — {{ config('app.name', 'Lumora Hosting') }}</title>
<style>html{visibility:hidden}</style>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4" onload="document.documentElement.style.visibility='visible'"></script>
<script>setTimeout(function(){document.documentElement.style.visibility='visible'},2500)</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style type="text/tailwindcss">
@theme {
  --font-sans: "Inter", sans-serif;
  --color-accent: #6366F1;
  --color-accent-soft: #818CF8;
  --shadow-rail: 0 0 16px 2px rgba(99,102,241,0.75);
}
</style>
</head>
<body class="antialiased font-sans bg-slate-50 min-h-screen flex items-center justify-center p-6">

  <div class="w-full max-w-sm">
    <div class="flex items-center gap-3 mb-8 justify-center">
      @php $otpLogo = \App\Models\Setting::get('site_logo'); @endphp
      @if ($otpLogo)
        <img src="{{ route('branding.file', $otpLogo) }}" alt="{{ config('app.name', 'Lumora Hosting') }}" class="h-11 w-auto object-contain">
      @else
        <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center">
          <svg viewBox="0 0 24 24" class="text-white" fill="none" stroke="currentColor" stroke-width="2.2" style="width:18px;height:18px"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
        </div>
        <span class="font-bold text-lg text-slate-800">{{ config('app.name', 'Lumora Hosting') }}</span>
      @endif
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>

      <h2 class="text-lg font-bold text-slate-800 mb-1">Verifikasi Dua Langkah</h2>
      <p class="text-sm text-slate-500 mb-5">
        Kami mengirim kode 6 digit ke <b>{{ $maskedEmail }}</b>. Masukkan kode tersebut untuk melanjutkan.
      </p>

      @if (session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-2.5 text-sm text-emerald-700">
          {{ session('success') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-2.5 text-sm text-rose-700">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin.otp.verify') }}" class="space-y-4">
        @csrf
        <div>
          <label for="code" class="block text-xs font-semibold text-slate-600 mb-1.5">Kode Verifikasi</label>
          <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                 autocomplete="one-time-code" placeholder="000000"
                 class="w-full px-3.5 py-3 rounded-lg border border-slate-200 text-center text-2xl font-bold tracking-[0.4em] outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition-all">
        </div>

        <button type="submit" class="w-full py-2.5 rounded-lg bg-accent text-white text-sm font-semibold hover:bg-accent-soft transition-colors shadow-[--shadow-rail]">
          Verifikasi &amp; Masuk
        </button>
      </form>

      <div class="flex items-center justify-between mt-5 pt-4 border-t border-slate-100 text-xs">
        <form method="POST" action="{{ route('admin.otp.resend') }}">
          @csrf
          <button type="submit" class="text-accent font-medium hover:underline">Kirim ulang kode</button>
        </form>
        <form method="POST" action="{{ route('admin.otp.cancel') }}">
          @csrf
          <button type="submit" class="text-slate-400 hover:text-slate-600">Batal, kembali ke login</button>
        </form>
      </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">
      Kode berlaku 10 menit. Jangan bagikan kode ini ke siapapun.
    </p>
  </div>

</body>
</html>
