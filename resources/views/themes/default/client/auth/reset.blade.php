@extends('client.auth.layout')
@section('title', 'Password Baru')

@section('form')
  <h2 class="text-xl font-bold text-slate-800 mb-1">Buat Password Baru</h2>
  <p class="text-sm text-slate-500 mb-6">Kode Anda sudah terverifikasi. Tentukan password baru di bawah ini.</p>

  @if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('client.password.update') }}" class="space-y-4">
    @csrf

    <div>
      <label for="password" class="form-label">Password Baru</label>
      <input id="password" name="password" type="password" required autofocus placeholder="••••••••" class="form-input">
      <p class="text-[11px] text-slate-400 mt-1">Minimal 8 karakter, mengandung huruf dan angka.</p>
    </div>

    <div>
      <label for="password_confirmation" class="form-label">Ulangi Password Baru</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="••••••••" class="form-input">
    </div>

    <button type="submit" class="w-full btn btn-primary">Simpan Password Baru</button>
  </form>
@endsection
