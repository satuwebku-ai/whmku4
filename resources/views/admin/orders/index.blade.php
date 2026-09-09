@extends('layouts.admin')

@section('title', 'Order')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Order</h1>
      <p class="small text-muted mb-0">Riwayat dan status pemesanan layanan.</p>
    </div>
    <a href="{{ route('admin.order.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Order
    </a>
  </div>

  {{-- Tab status --}}
  <div class="d-flex align-items-center gap-1 mb-3 border-bottom flex-wrap">
    @php
      $tabs = [
        ['label' => 'Semua', 'route' => 'admin.orders', 'status' => null],
        ['label' => 'Pending', 'route' => 'admin.orders.pending', 'status' => 'pending'],
        ['label' => 'Aktif', 'route' => 'admin.orders.active', 'status' => 'active'],
        ['label' => 'Suspended', 'route' => 'admin.orders.suspended', 'status' => 'suspended'],
        ['label' => 'Cancelled', 'route' => 'admin.orders.cancelled', 'status' => 'cancelled'],
      ];
    @endphp
    @foreach ($tabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ $activeStatus === $tab['status'] ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor order / produk..." class="form-control form-control-sm" style="max-width:20rem;flex:1 1 200px">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
      @if (request('search'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">ID Order</th>
            <th class="py-3">Klien</th>
            <th class="py-3">Produk</th>
            <th class="py-3">Status</th>
            <th class="text-end py-3">Total</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $statusBadge = [
              'active' => 'badge-soft-success', 'pending' => 'badge-soft-warning',
              'suspended' => 'badge-soft-danger', 'cancelled' => 'badge-soft-secondary',
            ];
          @endphp
          @forelse ($orders as $order)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <a href="{{ route('admin.orders.details', $order) }}" class="text-decoration-none text-dark">#{{ $order->order_number }}</a>
              </td>
              <td class="text-muted py-3">{{ $order->client->name ?? '—' }}</td>
              <td class="text-muted py-3">{{ $order->product_name }}</td>
              <td class="py-3"><span class="badge {{ $statusBadge[$order->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($order->status) }}</span></td>
              <td class="text-end text-dark py-3">Rp {{ number_format($order->amount, 0, ',', '.') }}</td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.orders.details', $order) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Detail">
                    <i class="fa-regular fa-eye" style="font-size:12px"></i>
                  </a>
                  <a href="{{ route('admin.order.edit.page', $order) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.order.delete', $order) }}" data-confirm="Hapus order ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada order di kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($orders->hasPages())
      <div class="px-4 py-3 border-top">{{ $orders->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
