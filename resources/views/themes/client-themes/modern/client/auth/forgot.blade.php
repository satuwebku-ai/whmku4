@extends('client.auth.layout')
@section('title', 'Lupa Password')

@section('form')
  <h2 class="text-xl font-bold text-slate-800 mb-1">Lupa Password</h2>
  <p class="text-sm text-slate-500 mb-6">Masukkan email akun Anda. Kami akan mengirim kode verifikasi ke sana.</p>

  @if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('client.password.email') }}" class="space-y-4">
    @csrf

    <div>
      <label for="email" class="form-label">Email Terdaftar</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
             placeholder="email@contoh.com" class="form-input">
    </div>

    <button type="submit" class="w-full btn btn-primary">Kirim Kode Reset</button>
  </form>

  <p class="text-center text-sm text-slate-500 mt-6">
    Ingat password Anda? <a href="{{ route('client.login') }}" class="text-accent font-medium hover:underline">Masuk</a>
  </p>
@endsection
