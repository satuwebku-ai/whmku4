@extends('public.layout')

@php($seoTitle = $license->name)

@section('content')
  <div class="row g-4 py-4">
    <div class="col-12 col-lg-7">
      <a href="{{ route('license.index') }}" class="small text-decoration-none"><i class="fa-solid fa-arrow-left"></i> Kembali ke Lisensi</a>
      <h1 class="fw-bold text-dark mt-3">{{ $license->name }}</h1>
      <div class="prose-content text-muted mt-3">
        {!! nl2br(e($license->description ?: 'Lisensi digital untuk kebutuhan operasional Anda.')) !!}
      </div>
      <div class="mt-4 p-4 rounded-4 bg-light border">
        <h2 class="h6 fw-bold">Yang Anda dapatkan</h2>
        <ul class="small text-muted mb-0">
          <li>Hak penggunaan sesuai paket dan siklus yang dipilih.</li>
          <li>Konfirmasi order dan invoice melalui panel client.</li>
          <li>Dukungan teknis sesuai kebijakan layanan.</li>
        </ul>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0 rounded-4 p-4 sticky-lg-top" style="top:6rem">
        <h2 class="h5 fw-bold">Pesan lisensi</h2>
        <p class="text-muted small">Pilih siklus pembayaran, lalu lanjutkan ke keranjang dan checkout.</p>
        <form method="POST" action="{{ route('cart.add-addon') }}">
          @csrf
          <input type="hidden" name="addon_id" value="{{ $license->id }}">
          <label class="form-label small fw-semibold">Siklus pembayaran</label>
          <select name="billing_cycle" class="form-select mb-3" required>
            @foreach ($license->availableCycles() as $cycle => $price)
              <option value="{{ $cycle }}">{{ \Illuminate\Support\Str::headline($cycle) }} — Rp {{ number_format($price, 0, ',', '.') }}</option>
            @endforeach
          </select>
          <button class="btn btn-primary w-100"><i class="fa-solid fa-cart-plus me-1"></i> Tambahkan ke Keranjang</button>
        </form>
        <p class="text-muted mt-3 mb-0" style="font-size:11px">Harga modal supplier tidak pernah ditampilkan atau dibebankan ke client.</p>
      </div>
    </div>
  </div>
@endsection