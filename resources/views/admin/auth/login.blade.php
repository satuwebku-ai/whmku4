<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — {{ config('app.name', 'Lumora Hosting') }}</title>

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
@layer components {
  .bg-auth {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 35%, #4c1d95 70%, #1e1b4b 100%);
  }
}
</style>
</head>
<body class="antialiased font-sans">

<div class="min-h-screen flex">

  {{-- Left branding panel --}}
  <div class="hidden lg:flex lg:w-1/2 bg-auth relative overflow-hidden items-center justify-center p-12">
    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 32px 32px;"></div>
    <div class="relative text-center max-w-md">
      @php
        $loginLogo = \App\Models\Setting::get('site_logo');
        $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text');
      @endphp
      @if ($brandingDisplay !== 'text_only')
        @if ($loginLogo)
          <img src="{{ route('branding.file', $loginLogo) }}" alt="{{ config('app.name', 'Lumora Hosting') }}" class="h-20 w-auto object-contain mx-auto mb-6">
        @else
          <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur flex items-center justify-center mx-auto mb-6 shadow-[--shadow-rail]">
            <svg viewBox="0 0 24 24" class="text-white" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px">
              <path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/>
            </svg>
          </div>
        @endif
      @endif
      @if ($brandingDisplay !== 'logo_only')
        <h1 class="text-white text-2xl font-bold mb-3">{{ config('app.name', 'Lumora Hosting') }}</h1>
      @endif
      <p class="text-white/60 text-sm leading-relaxed">
        Panel admin untuk mengelola klien, order, invoice, domain, dan layanan hosting — semua dalam satu tempat.
      </p>
    </div>
  </div>

  {{-- Right form panel --}}
  <div class="flex-1 flex items-center justify-center p-6 bg-slate-50">
    <div class="w-full max-w-sm">
      <div class="lg:hidden flex items-center gap-3 mb-8 justify-center">
        @php $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text'); @endphp
        @if ($loginLogo && $brandingDisplay !== 'text_only')
          <img src="{{ route('branding.file', $loginLogo) }}" alt="{{ config('app.name', 'Lumora Hosting') }}" class="h-11 w-auto object-contain">
        @elseif ($brandingDisplay !== 'text_only')
          <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center">
            <svg viewBox="0 0 24 24" class="text-white" fill="none" stroke="currentColor" stroke-width="2.2" style="width:18px;height:18px"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
          </div>
        @endif
        @if ($brandingDisplay !== 'logo_only')
          <span class="font-bold text-lg text-slate-800">{{ config('app.name', 'Lumora Hosting') }}</span>
        @endif
      </div>

      <h2 class="text-xl font-bold text-slate-800 mb-1">Masuk ke Admin Panel</h2>
      <p class="text-sm text-slate-500 mb-6">Gunakan username &amp; password admin Anda.</p>

      @if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
        @csrf

        <div>
          <label for="username" class="block text-xs font-semibold text-slate-600 mb-1.5">Username</label>
          <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus
                 placeholder="admin"
                 class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition-all">
        </div>

        <div>
          <label for="password" class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
          <input id="password" name="password" type="password" required
                 placeholder="••••••••"
                 class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition-all">
        </div>

        <div class="flex items-center justify-between text-sm">
          <label class="flex items-center gap-2 text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-accent focus:ring-accent/40">
            Ingat saya
          </label>
          <a href="#" class="text-accent font-medium hover:underline">Lupa password?</a>
        </div>

        @include('partials.captcha')


        <button type="submit"
                class="w-full py-2.5 rounded-lg bg-accent text-white text-sm font-semibold hover:bg-accent-soft transition-colors shadow-[--shadow-rail]">
          Masuk
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-8">
        &copy; {{ date('Y') }} {{ config('app.name', 'Lumora Hosting') }}. Semua hak dilindungi.
      </p>
    </div>
  </div>
</div>

</body>
</html>
