@extends('client.auth.layout')
@section('title', 'Verifikasi Kode')

@section('form')
  <h2 class="text-xl font-bold text-slate-800 mb-1">Masukkan Kode</h2>
  <p class="text-sm text-slate-500 mb-6">
    Kami mengirim kode 6 digit ke <b>{{ $email }}</b>. Kode berlaku 15 menit.
  </p>

  @if (session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('client.password.verify.code') }}" class="space-y-4">
    @csrf

    <div>
      <label for="code" class="form-label">Kode Verifikasi</label>
      <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
             required autofocus autocomplete="one-time-code" placeholder="000000"
             class="w-full px-3.5 py-3 rounded-lg border border-slate-200 text-center text-2xl font-bold tracking-[0.4em] outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
    </div>

    <button type="submit" class="w-full btn btn-primary">Verifikasi Kode</button>
  </form>

  <p class="text-center text-sm text-slate-500 mt-6">
    Tidak menerima kode?
    <a href="{{ route('client.password.request') }}" class="text-accent font-medium hover:underline">Kirim ulang</a>
  </p>
@endsection
