@extends('layouts.admin')

@section('title', 'Payment Gateway')

@section('content')

  {{-- Tab atas: Transaksi vs Gateway --}}
  <div class="d-flex align-items-center gap-1 mb-3 border-bottom flex-wrap">
    @php
      $topTabs = [
        ['label' => 'Transaksi', 'route' => 'admin.payments'],
        ['label' => 'Gateway', 'route' => 'admin.gateways'],
      ];
    @endphp
    @foreach ($topTabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route']) . '*') ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Payment Gateway</h1>
      <p class="small text-muted mb-0">Kelola metode pembayaran yang tersedia. Kredensial dienkripsi otomatis.</p>
    </div>
    <a href="{{ route('admin.gateway.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Gateway
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="py-3">Driver</th>
            <th class="py-3">Mode</th>
            <th class="py-3">Biaya</th>
            <th class="text-center py-3">Transaksi</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($gateways as $gw)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">{{ $gw->name }}</td>
              <td class="text-muted py-3">{{ $gw->driver_label }}</td>
              <td class="py-3">
                @if ($gw->isManual())
                  <span class="text-muted" style="font-size:12px">—</span>
                @else
                  <span class="badge {{ $gw->isSandbox() ? 'badge-soft-warning' : 'badge-soft-success' }}">{{ $gw->isSandbox() ? 'Sandbox' : 'Production' }}</span>
                @endif
              </td>
              <td class="text-muted py-3" style="font-size:12px">
                @if ($gw->fee_flat > 0 || $gw->fee_percent > 0)
                  Rp {{ number_format($gw->fee_flat, 0, ',', '.') }} + {{ rtrim(rtrim(number_format($gw->fee_percent, 2), '0'), '.') }}%
                @else
                  Gratis
                @endif
              </td>
              <td class="text-center text-muted py-3">{{ $gw->payments_count }}</td>
              <td class="py-3">
                <span class="badge {{ $gw->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $gw->is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <form method="POST" action="{{ route('admin.gateway.status') }}">
                    @csrf
                    <input type="hidden" name="gateway_id" value="{{ $gw->id }}">
                    <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="{{ $gw->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                      <i class="fa-solid {{ $gw->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}" style="font-size:12px"></i>
                    </button>
                  </form>
                  <a href="{{ route('admin.gateway.edit.page', $gw) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.gateway.delete', $gw) }}" data-confirm="Hapus gateway ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">Belum ada payment gateway. Tambahkan minimal satu supaya bisa menerima pembayaran.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($gateways->hasPages())
      <div class="px-4 py-3 border-top">{{ $gateways->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
