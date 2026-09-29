@extends('public.layout')

@php($seoTitle = 'Lisensi')

@section('content')
  <section class="py-4">
    <div class="text-center mb-5">
      <span class="badge bg-primary-subtle text-primary mb-2">LISENSI</span>
      <h1 class="fw-bold text-dark">Lisensi dan Add-on Digital</h1>
      <p class="text-muted mb-0">Pilih lisensi yang Anda butuhkan. Harga dan siklus pembayaran ditampilkan transparan sebelum checkout.</p>
    </div>
    <div class="row g-4">
      @forelse ($licenses as $license)
        <div class="col-12 col-md-6 col-xl-4">
          <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-4 d-flex flex-column">
              <div class="rounded-3 bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width:46px;height:46px">
                <i class="fa-solid fa-key"></i>
              </div>
              <h2 class="h5 fw-bold">{{ $license->name }}</h2>
              <p class="text-muted small flex-grow-1">{{ $license->description ?: 'Lisensi digital dengan aktivasi dan dukungan sesuai ketentuan layanan.' }}</p>
              <div class="mb-3">
                @foreach ($license->availableCycles() as $cycle => $price)
                  <span class="badge bg-light text-dark border me-1 mb-1">{{ \Illuminate\Support\Str::headline($cycle) }}: Rp {{ number_format($price, 0, ',', '.') }}</span>
                @endforeach
              </div>
              <a href="{{ route('license.show', $license->slug) }}" class="btn btn-primary w-100">Lihat Detail & Pesan</a>
            </div>
          </article>
        </div>
      @empty
        <div class="col-12"><div class="alert alert-light border text-center">Belum ada lisensi yang tersedia.</div></div>
      @endforelse
    </div>
  </section>
@endsection