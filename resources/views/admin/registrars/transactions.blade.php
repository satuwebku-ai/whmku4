@extends('layouts.admin')

@section('title', 'Riwayat Transaksi — ' . $registrar->name)

@section('content')

  <a href="{{ route('admin.registrars.index') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Registrar</a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-4">Riwayat Transaksi — {{ $registrar->name }}</h1>

  @if ($warning)
    <div class="card border rounded-4 p-3 mb-4" style="border-color:#fde68a!important;background:#fffbeb">
      <p class="mb-0" style="font-size:14px;color:#92400e"><i class="fa-solid fa-triangle-exclamation"></i> {{ $warning }}</p>
    </div>
  @endif

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Tanggal</th>
            <th class="py-3">Jenis</th>
            <th class="py-3">Keterangan</th>
            <th class="text-end px-4 py-3">Jumlah</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($transactions as $tx)
            <tr>
              <td class="px-4 py-3 text-muted" style="font-size:12px">{{ $tx['date'] ?? '—' }}</td>
              <td class="py-3"><span class="badge badge-soft-secondary">{{ $tx['type'] }}</span></td>
              <td class="py-3 text-dark">{{ $tx['description'] }}</td>
              <td class="text-end px-4 py-3 fw-medium {{ $tx['amount'] < 0 ? 'text-danger' : 'text-success' }}">
                {{ $tx['amount'] >= 0 ? '+' : '' }}Rp {{ number_format($tx['amount'], 0, ',', '.') }}
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-5">Tidak ada transaksi ditemukan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between">
      <p class="text-muted mb-0" style="font-size:11px">Halaman {{ $page }}</p>
      <div class="d-flex gap-2">
        @if ($page > 1)
          <a href="{{ route('admin.registrars.transactions', [$registrar, 'page' => $page - 1]) }}" class="btn btn-outline-secondary btn-sm">Sebelumnya</a>
        @endif
        @if (count($transactions) === 25)
          <a href="{{ route('admin.registrars.transactions', [$registrar, 'page' => $page + 1]) }}" class="btn btn-outline-secondary btn-sm">Berikutnya</a>
        @endif
      </div>
    </div>
  </div>

@endsection
