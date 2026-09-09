@php
  use App\Models\Setting;
  $siteName = Setting::get('site_name', config('app.name', 'Lumora Hosting'));
  $tagline = Setting::get('site_tagline', 'Hosting & Domain Terpercaya');
  $themeColor = Setting::get('theme_color', '#6366F1');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') — {{ $siteName }}</title>

<link rel="stylesheet" href="{{ asset('assets/css/vendor/bootstrap-5.3.8.min.css') }}?v={{ @filemtime(public_path('assets/css/vendor/bootstrap-5.3.8.min.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/lumora-public.css') }}?v={{ @filemtime(public_path('assets/css/lumora-public.css')) ?: time() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
  :root{ --lumora-theme: {{ $themeColor }}; }
  html, body{ height:100%; font-family:'Inter', sans-serif; }
  .bg-auth{ background:linear-gradient(135deg,#1e1b4b 0%,#312e81 35%,#4c1d95 70%,#1e1b4b 100%); }
  .bg-auth-dots{ background-image:radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size:32px 32px; opacity:.2; }
</style>
</head>
<body>

<div class="d-flex" style="min-height:100vh">

  <div class="d-none d-lg-flex bg-auth position-relative overflow-hidden align-items-center justify-content-center p-5" style="width:50%">
    <div class="position-absolute top-0 start-0 end-0 bottom-0 bg-auth-dots"></div>
    <div class="position-relative text-center" style="max-width:26rem">
      @php
        $loginLogo = \App\Models\Setting::get('site_logo');
        $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text');
      @endphp
      @if ($brandingDisplay !== 'text_only')
        @if ($loginLogo)
          <img src="{{ route('branding.file', $loginLogo) }}" alt="{{ $siteName }}" class="mb-4" style="height:80px;width:auto;object-fit:contain">
        @else
          <div class="rounded-4 d-flex align-items-center justify-content-center mx-auto mb-4" style="width:64px;height:64px;background:rgba(255,255,255,.1)">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
          </div>
        @endif
      @endif
      @if ($brandingDisplay !== 'logo_only')
        <h1 class="text-white fw-bold mb-3" style="font-size:1.6rem">{{ $siteName }}</h1>
      @endif
      <p class="mb-0" style="color:rgba(255,255,255,.6);font-size:14px;line-height:1.7">{{ $tagline }}</p>

      <div class="mt-4 d-flex flex-column gap-3 text-start">
        @foreach (['Kelola layanan hosting & domain', 'Lihat dan bayar invoice online', 'Ajukan tiket support kapan saja'] as $feature)
          <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,.7);font-size:14px">
            <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:20px;height:20px;background:rgba(255,255,255,.15);font-size:10px">&check;</span>
            {{ $feature }}
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4" style="background:#f8fafc;overflow-y:auto">
    <div class="w-100 py-4" style="max-width:26rem">
      <div class="d-lg-none d-flex align-items-center gap-3 mb-4 justify-content-center">
        @if ($loginLogo)
          <img src="{{ route('branding.file', $loginLogo) }}" alt="{{ $siteName }}" style="height:44px;width:auto;object-fit:contain">
        @else
          <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:var(--lumora-theme)">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
          </div>
          <span class="fw-bold text-dark" style="font-size:1.1rem">{{ $siteName }}</span>
        @endif
      </div>

      @if (session('success'))
        <div class="rounded-3 px-3 py-2 mb-4" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:14px;color:#15803d">
          {{ session('success') }}
        </div>
      @endif

      @yield('form')

      <p class="text-center text-muted mt-5 mb-0" style="font-size:12px">
        &copy; {{ date('Y') }} {{ $siteName }}
      </p>
    </div>
  </div>
</div>

</body>
</html>
