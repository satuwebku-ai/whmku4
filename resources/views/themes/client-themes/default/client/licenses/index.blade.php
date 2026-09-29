@extends('client.layout')

@section('title', 'Lisensi Saya')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div><h1 class="h4 fw-bold mb-1">Lisensi Saya</h1><p class="text-muted small mb-0">Riwayat lisensi yang dipesan melalui katalog publik.</p></div>
    <a href="{{ route('license.index') }}" class="btn btn-primary btn-sm">Beli Lisensi</a>
  </div>
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th class="px-4">Lisensi</th><th>Siklus</th><th>Status Order</th><th class="text-end px-4">Nominal</th></tr></thead>
        <tbody>
          @forelse ($licenses as $license)
            <tr>
              <td class="px-4 fw-semibold">{{ $license->product_name }}</td>
              <td class="text-muted">Order {{ $license->order_number }}</td>
              <td><span class="badge text-bg-light">{{ $license->status instanceof \BackedEnum ? $license->status->value : $license->status }}</span></td>
              <td class="text-end px-4">Rp {{ number_format((float) $license->amount, 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-5">Belum ada order lisensi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($licenses->hasPages()) <div class="p-3 border-top">{{ $licenses->links('pagination.bootstrap') }}</div> @endif
  </div>
@endsection