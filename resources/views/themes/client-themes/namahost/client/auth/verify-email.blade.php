@extends('client.auth.layout')
@section('title', 'Verifikasi Email')

@section('form')
  <span class="rounded-4 d-flex align-items-center justify-content-center mb-4" style="width:48px;height:48px;background:rgba(79,70,229,.1);color:#4f46e5">
    <i class="fa-solid fa-envelope-circle-check" style="font-size:18px"></i>
  </span>

  <h2 class="fw-bold text-dark mb-1" style="font-size:1.4rem">Verifikasi Email Anda</h2>
  <p class="text-muted mb-4">
    Kami mengirim kode 6 digit ke <b class="text-dark">{{ $email }}</b>. Masukkan kode itu untuk mengaktifkan akun.
  </p>

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

  <form method="POST" action="{{ route('client.verify.submit') }}">
    @csrf

    <div class="mb-3">
      <label for="code" class="form-label">Kode Verifikasi</label>
      <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
             autocomplete="one-time-code" placeholder="000000" class="form-control text-center"
             style="font-size:1.75rem;font-weight:700;letter-spacing:.5em;padding:.75rem 0 .75rem .5em">
    </div>

    <button type="submit" class="btn btn-theme w-100">
      Verifikasi &amp; Aktifkan Akun
    </button>
  </form>

  <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top" style="font-size:13px">
    <form method="POST" action="{{ route('client.verify.resend') }}">
      @csrf
      <button type="submit" class="btn btn-link p-0 text-theme fw-medium" style="text-decoration:underline;font-size:13px">Kirim ulang kode</button>
    </form>
    <a href="{{ route('client.login') }}" class="text-muted text-decoration-none">Kembali ke masuk</a>
  </div>

  <p class="text-center text-muted mt-4 mb-0" style="font-size:11px">
    Tidak menerima email? Periksa folder spam. Kode berlaku 30 menit.
  </p>
@endsection
