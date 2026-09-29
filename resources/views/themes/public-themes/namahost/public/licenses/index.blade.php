@extends('public.layout')

@php($seoTitle = 'Lisensi')

@section('content')
  <section class="py-5">
    <div class="text-center mb-5">
      <span class="badge badge-soft-warning mb-2">LISENSI DIGITAL</span>
      <h1 class="display-6 fw-bold text-dark">Lisensi yang siap dipakai</h1>
      <p class="text-muted mb-0">Informasi fitur, hak penggunaan, dukungan, dan harga tersedia dalam satu halaman.</p>
    </div>
    <div class="row g-4">
      @forelse ($licenses as $license)
        <div class="col-12 col-md-6 col-xl-4">
          <article class="card-public h-100 p-4 d-flex flex-column">
            <div class="tile t-indigo mb-3"><i class="fa-solid fa-key"></i></div>
            <h2 class="h5 fw-bold text-dark">{{ $license->name }}</h2>
            <p class="text-muted small flex-grow-1">{{ $license->description ?: 'Lisensi digital dengan aktivasi dan dukungan sesuai ketentuan layanan.' }}</p>
            <div class="border-top pt-3 mb-3">
              @foreach ($license->availableCycles() as $cycle => $price)
                <div class="d-flex justify-content-between small mb-1">
                  <span class="text-muted">{{ ['monthly'=>'Bulanan','quarterly'=>'3 Bulan','semi_annually'=>'6 Bulan','annually'=>'Tahunan'][$cycle] ?? $cycle }}</span>
                  <strong>Rp {{ number_format($price, 0, ',', '.') }}</strong>
                </div>
              @endforeach
            </div>
            <a href="{{ route('license.show', $license->slug) }}" class="btn btn-theme w-100">Lihat Detail & Pesan</a>
          </article>
        </div>
      @empty
        <div class="col-12"><div class="card-public p-5 text-center text-muted">Belum ada lisensi yang tersedia.</div></div>
      @endforelse
    </div>
  </section>
@endsection