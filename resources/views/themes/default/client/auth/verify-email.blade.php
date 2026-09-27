@extends('client.auth.layout')
@section('title', 'Verifikasi Email')

@section('form')
  <h2 class="text-xl font-bold text-slate-800 mb-1">Verifikasi Email Anda</h2>
  <p class="text-sm text-slate-500 mb-6">
    Kami mengirim kode 6 digit ke <b>{{ $email }}</b>. Masukkan kode itu untuk mengaktifkan akun.
  </p>

  @if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('client.verify.submit') }}" class="space-y-4">
    @csrf
    <div>
      <label for="code" class="form-label">Kode Verifikasi</label>
      <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
             required autofocus autocomplete="one-time-code" placeholder="000000"
             class="w-full px-3.5 py-3 rounded-lg border border-slate-200 text-center text-2xl font-bold tracking-[0.4em] outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
    </div>

    <button type="submit" class="w-full btn btn-primary">Verifikasi &amp; Aktifkan Akun</button>
  </form>

  <div class="flex items-center justify-between mt-6 text-sm">
    <form method="POST" action="{{ route('client.verify.resend') }}">
      @csrf
      <button type="submit" class="text-accent font-medium hover:underline">Kirim ulang kode</button>
    </form>
    <a href="{{ route('client.login') }}" class="text-slate-400 hover:text-slate-600">Kembali ke masuk</a>
  </div>

  <p class="text-[11px] text-slate-400 mt-6">
    Tidak menerima email? Periksa folder spam. Kode berlaku 30 menit.
  </p>
@endsection
