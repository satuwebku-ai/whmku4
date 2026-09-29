@extends('public.layout')

@php($seoTitle = $license->name)

@section('content')
  <div class="row g-4 py-5">
    <div class="col-12 col-lg-7">
      <a href="{{ route('license.index') }}" class="small text-decoration-none text-theme"><i class="fa-solid fa-arrow-left"></i> Semua Lisensi</a>
      <div class="tile t-indigo mt-4 mb-3"><i class="fa-solid fa-key"></i></div>
      <h1 class="display-6 fw-bold text-dark">{{ $license->name }}</h1>
      <div class="prose-content text-muted mt-3">
        {!! nl2br(e($license->description ?: 'Lisensi digital untuk kebutuhan operasional Anda.')) !!}
      </div>
      <div class="feat mt-4">
        <h2 class="h6 fw-bold text-dark">Termasuk dalam lisensi</h2>
        <ul class="small text-muted mb-0">
          <li>Hak penggunaan sesuai paket dan siklus yang dipilih.</li>
          <li>Invoice dan status order tersimpan di panel client.</li>
          <li>Dukungan teknis sesuai kebijakan layanan.</li>
        </ul>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card-public p-4 sticky-lg-top" style="top:6rem">
        <h2 class="h5 fw-bold text-dark">Pesan sekarang</h2>
        <p class="text-muted small">Pilih siklus pembayaran untuk melanjutkan ke checkout.</p>
        <form method="POST" action="{{ route('cart.add-addon') }}">
          @csrf
          <input type="hidden" name="addon_id" value="{{ $license->id }}">
          <label class="form-label small fw-semibold">Siklus pembayaran</label>
          <select name="billing_cycle" class="form-select mb-3" required>
            @foreach ($license->availableCycles() as $cycle => $price)
              <option value="{{ $cycle }}">{{ ['monthly'=>'Bulanan','quarterly'=>'3 Bulan','semi_annually'=>'6 Bulan','annually'=>'Tahunan'][$cycle] ?? $cycle }} — Rp {{ number_format($price, 0, ',', '.') }}</option>
            @endforeach
          </select>
          <button class="btn btn-theme w-100"><i class="fa-solid fa-cart-plus me-1"></i> Tambahkan ke Keranjang</button>
        </form>
        <p class="text-muted mt-3 mb-0" style="font-size:11px">Harga modal supplier adalah data internal dan tidak ditampilkan kepada client.</p>
      </div>
    </div>
  </div>
@endsection