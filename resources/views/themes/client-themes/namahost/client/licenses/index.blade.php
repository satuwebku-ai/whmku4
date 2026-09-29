@extends('client.layout')

@section('title', 'Lisensi Saya')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div><h1 class="h4 fw-bold mb-1">Lisensi Saya</h1><p class="text-muted small mb-0">Status order dan tagihan lisensi Anda.</p></div>
    <a href="{{ route('license.index') }}" class="btn btn-theme btn-sm"><i class="fa-solid fa-plus me-1"></i> Beli Lisensi</a>
  </div>
  <div class="row g-3">
    @forelse ($licenses as $license)
      <div class="col-12 col-md-6">
        <div class="card-public p-4 h-100">
          <div class="d-flex align-items-start gap-3">
            <span class="tile t-indigo"><i class="fa-solid fa-key"></i></span>
            <div class="min-w-0 flex-grow-1">
              <h2 class="h6 fw-bold text-dark mb-1">{{ $license->product_name }}</h2>
              <p class="text-muted mb-2" style="font-size:11px">Order {{ $license->order_number }}</p>
              <span class="badge badge-soft-success">{{ $license->status instanceof \BackedEnum ? $license->status->value : $license->status }}</span>
            </div>
            <strong class="text-dark">Rp {{ number_format((float) $license->amount, 0, ',', '.') }}</strong>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12"><div class="card-public p-5 text-center text-muted">Belum ada order lisensi.</div></div>
    @endforelse
  </div>
  @if ($licenses->hasPages()) <div class="mt-4">{{ $licenses->links('pagination.bootstrap') }}</div> @endif
@endsection