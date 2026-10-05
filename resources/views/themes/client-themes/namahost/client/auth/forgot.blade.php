@extends('client.auth.layout')
@section('title', 'Lupa Password')

@section('form')
  <span class="rounded-4 d-flex align-items-center justify-content-center mb-4" style="width:48px;height:48px;background:rgba(79,70,229,.1);color:#4f46e5">
    <i class="fa-solid fa-key" style="font-size:18px"></i>
  </span>

  <h2 class="fw-bold text-dark mb-1" style="font-size:1.4rem">Lupa Password</h2>
  <p class="text-muted mb-4">Masukkan email akun Anda. Kami akan mengirim kode verifikasi ke sana.</p>

  @if (session('success'))
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:14px;color:#15803d">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#fef2f2;border:1px solid #fecaca;font-size:14px;color:#b91c1c">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('client.password.email') }}">
    @csrf

    <div class="mb-3">
      <label for="email" class="form-label">Email Terdaftar</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
             autocomplete="email" placeholder="email@contoh.com" class="form-control">
    </div>

    <button type="submit" class="btn btn-theme w-100">
      Kirim Kode Reset
    </button>
  </form>

  <p class="text-center text-muted mt-4 mb-0" style="font-size:14px">
    Ingat password Anda?
    <a href="{{ route('client.login') }}" class="text-decoration-none text-theme fw-medium">Masuk</a>
  </p>
@endsection
