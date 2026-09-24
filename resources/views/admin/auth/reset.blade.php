<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Baru — {{ config('app.name', 'Lumora Hosting') }}</title>
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
      @php $rpLogo = \App\Models\Setting::get('site_logo'); @endphp
      @if ($rpLogo)
        <img src="{{ route('branding.file', $rpLogo) }}" alt="{{ config('app.name', 'Lumora Hosting') }}" class="h-11 w-auto object-contain">
      @else
        <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center">
          <svg viewBox="0 0 24 24" class="text-white" fill="none" stroke="currentColor" stroke-width="2.2" style="width:18px;height:18px"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
        </div>
        <span class="font-bold text-lg text-slate-800">{{ config('app.name', 'Lumora Hosting') }}</span>
      @endif
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <h2 class="text-lg font-bold text-slate-800 mb-1">Buat Password Baru</h2>
      <p class="text-sm text-slate-500 mb-5">Kode Anda sudah terverifikasi. Tentukan password baru di bawah ini.</p>

      @if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-2.5 text-sm text-rose-700">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin.password.update') }}" class="space-y-4">
        @csrf
        <div>
          <label for="password" class="block text-xs font-semibold text-slate-600 mb-1.5">Password Baru</label>
          <input id="password" name="password" type="password" required autofocus placeholder="••••••••"
                 class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition-all">
          <p class="text-[11px] text-slate-400 mt-1">Minimal 8 karakter, mengandung huruf dan angka.</p>
        </div>

        <div>
          <label for="password_confirmation" class="block text-xs font-semibold text-slate-600 mb-1.5">Ulangi Password Baru</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="••••••••"
                 class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition-all">
        </div>

        <button type="submit" class="w-full py-2.5 rounded-lg bg-accent text-white text-sm font-semibold hover:bg-accent-soft transition-colors shadow-[--shadow-rail]">
          Simpan Password Baru
        </button>
      </form>
    </div>
  </div>

</body>
</html>
