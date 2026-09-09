@extends('layouts.admin')

@section('title', 'Pembayaran')

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

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Transaksi Pembayaran</h1>
      <p class="small text-muted mb-0">Riwayat pembayaran invoice dari semua gateway.</p>
    </div>
    <a href="{{ route('admin.payment.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Pembayaran
    </a>
  </div>

  {{-- Tab status (bentuk pill) --}}
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    @php
      $statusTabs = [
        ['label' => 'Semua', 'route' => 'admin.payments', 'status' => null],
        ['label' => 'Menunggu Bayar', 'route' => 'admin.payments.initiated', 'status' => 'initiated'],
        ['label' => 'Perlu Verifikasi', 'route' => 'admin.payments.pending', 'status' => 'pending'],
        ['label' => 'Lunas', 'route' => 'admin.payments.paid', 'status' => 'paid'],
        ['label' => 'Gagal', 'route' => 'admin.payments.failed', 'status' => 'failed'],
        ['label' => 'Refund', 'route' => 'admin.payments.refunded', 'status' => 'refunded'],
      ];
    @endphp
    @foreach ($statusTabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none rounded-pill {{ $activeStatus === $tab['status'] ? 'text-white' : 'text-muted' }}"
         style="{{ $activeStatus === $tab['status'] ? 'background:#4f46e5' : 'background:#f1f5f9' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor referensi..." class="form-control form-control-sm" style="max-width:16rem;flex:1 1 180px">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
      @if (request('search'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Referensi</th>
            <th class="py-3">Klien</th>
            <th class="py-3">Invoice</th>
            <th class="py-3">Gateway</th>
            <th class="py-3">Status</th>
            <th class="text-end py-3">Total</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $badgeMap = ['paid' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'inactive' => 'badge-soft-secondary', 'suspended' => 'badge-soft-danger'];
          @endphp
          @forelse ($payments as $payment)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <a href="{{ route('admin.payments.details', $payment) }}" class="text-decoration-none text-dark">{{ $payment->reference }}</a>
                @if ($payment->proof_path)
                  <span class="badge badge-soft-success ms-1" style="font-size:10px" title="Bukti transfer sudah diunggah klien">
                    <i class="fa-solid fa-receipt"></i> Ada Bukti
                  </span>
                @endif
              </td>
              <td class="text-muted py-3">{{ $payment->client->name ?? '—' }}</td>
              <td class="text-muted py-3">
                @if ($payment->invoice)
                  <a href="{{ route('admin.invoices.details', $payment->invoice) }}" class="text-decoration-none text-accent">{{ $payment->invoice->invoice_number }}</a>
                @else
                  —
                @endif
              </td>
              <td class="text-muted py-3">{{ $payment->gateway->name ?? '—' }}</td>
              <td class="py-3"><span class="badge {{ $badgeMap[$payment->status_badge] ?? 'badge-soft-secondary' }}">{{ ucfirst($payment->status) }}</span></td>
              <td class="text-end text-dark py-3">Rp {{ number_format($payment->total, 0, ',', '.') }}</td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.payments.details', $payment) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Detail">
                    <i class="fa-regular fa-eye" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.payment.delete', $payment) }}" data-confirm="Hapus data pembayaran ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">Tidak ada pembayaran di kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($payments->hasPages())
      <div class="px-4 py-3 border-top">{{ $payments->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
