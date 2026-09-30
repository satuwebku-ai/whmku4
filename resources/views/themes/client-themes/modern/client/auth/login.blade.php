@extends('client.auth.layout')
@section('title', 'Masuk')

@section('form')
  <h2 class="fw-bold text-dark mb-1" style="font-size:1.4rem">Masuk ke Akun Anda</h2>
  <p class="text-muted mb-4">Kelola layanan hosting dan domain Anda.</p>

  @if ($errors->any())
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#fef2f2;border:1px solid #fecaca;font-size:14px;color:#b91c1c">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('client.login.store') }}">
    @csrf

    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
             placeholder="email@contoh.com" class="form-control">
    </div>

    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input id="password" name="password" type="password" required placeholder="••••••••" class="form-control">
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
      <label class="d-flex align-items-center gap-2 small text-dark mb-0">
        <input type="checkbox" name="remember" class="form-check-input" style="margin-top:0">
        Ingat saya
      </label>
      <a href="{{ route('client.password.request') }}" class="text-decoration-none text-theme fw-medium" style="font-size:14px">Lupa password?</a>
    </div>

    @include('partials.captcha')

    <button type="submit" class="btn btn-theme w-100">
      Masuk
    </button>
  </form>

  @if (config('services.google.client_id') && config('services.google.client_secret'))
    <div class="d-flex align-items-center gap-3 my-4">
      <span class="flex-grow-1 border-top"></span>
      <span class="text-muted" style="font-size:12px">atau</span>
      <span class="flex-grow-1 border-top"></span>
    </div>

    <a href="{{ route('client.google.redirect') }}"
       class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
      <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.83.86-3.06.86-2.35 0-4.34-1.59-5.05-3.72H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.95 10.7A5.4 5.4 0 0 1 3.67 9c0-.59.1-1.17.28-1.7V4.97H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.03l2.99-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.97l2.99 2.33C4.66 5.17 6.65 3.58 9 3.58z"/></svg>
      Masuk dengan Google
    </a>
  @endif

  <p class="text-center text-muted mt-4 mb-0" style="font-size:14px">
    Belum punya akun?
    <a href="{{ route('client.register') }}" class="text-decoration-none text-theme fw-medium">Daftar sekarang</a>
  </p>
@endsection
