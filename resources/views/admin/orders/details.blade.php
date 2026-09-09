@extends('layouts.admin')

@section('title', 'Detail Order #' . $order->order_number)

@section('content')

  @php
    $statusBadge = [
      'active' => 'badge-soft-success', 'pending' => 'badge-soft-warning',
      'suspended' => 'badge-soft-danger', 'cancelled' => 'badge-soft-secondary',
    ];
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <a href="{{ route('admin.orders') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Order</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-0">Order #{{ $order->order_number }}</h1>
    </div>
    <span class="badge {{ $statusBadge[$order->status] ?? 'badge-soft-secondary' }}" style="font-size:13px;padding:.4rem .8rem">{{ ucfirst($order->status) }}</span>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">

      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-3">Informasi Order</h2>
        <div class="row g-3 small">
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">KLIEN</p>
            <p class="fw-medium text-dark mb-0">{{ $order->client->name ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">PRODUK</p>
            <p class="fw-medium text-dark mb-0">{{ $order->product_name }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">TIPE</p>
            <p class="fw-medium text-dark mb-0 text-capitalize">{{ $order->order_type }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">JUMLAH</p>
            <p class="fw-medium text-dark mb-0">Rp {{ number_format($order->amount, 0, ',', '.') }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">HOSTING ACCOUNT TERKAIT</p>
            <p class="fw-medium text-dark mb-0">
              @if ($order->hostingAccount)
                <a href="{{ route('admin.hosting-accounts.details', $order->hostingAccount) }}" class="text-decoration-none text-accent">{{ $order->hostingAccount->domain }}</a>
              @else
                —
              @endif
            </p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">DOMAIN TERKAIT</p>
            <p class="fw-medium text-dark mb-0">
              @if ($order->domain)
                <a href="{{ route('admin.domains.details', $order->domain) }}" class="text-decoration-none text-accent">{{ $order->domain->domain_name }}</a>
                @php
                  $domainBadge = $order->domain->provision_status === 'registered' ? 'badge-soft-success' : ($order->domain->provision_status === 'failed' ? 'badge-soft-danger' : 'badge-soft-warning');
                @endphp
                <span class="badge {{ $domainBadge }} ms-1">{{ $order->domain->provision_status }}</span>
              @else
                —
              @endif
            </p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">INVOICE TERKAIT</p>
            <p class="fw-medium text-dark mb-0">
              @php $orderInvoice = $order->resolvedInvoice(); @endphp
              @if ($orderInvoice)
                <a href="{{ route('admin.invoices.details', $orderInvoice) }}" class="text-decoration-none text-accent">{{ $orderInvoice->invoice_number }}</a>
              @else
                —
              @endif
            </p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">DIBUAT</p>
            <p class="fw-medium text-dark mb-0">{{ $order->created_at->format('d M Y H:i') }}</p>
          </div>
        </div>
      </div>

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Catatan Internal</h2>
        <form method="POST" action="{{ route('admin.order.notes') }}">
          @csrf
          <input type="hidden" name="order_id" value="{{ $order->id }}">
          <textarea name="internal_notes" rows="4" class="form-control form-control-sm" placeholder="Catatan staf tentang order ini (tidak terlihat klien)...">{{ old('internal_notes', $order->internal_notes) }}</textarea>
          <button type="submit" class="btn btn-outline-secondary btn-sm mt-2"><i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Catatan</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Aksi</h2>
        <div class="d-flex flex-column gap-2">
          @if ($order->status !== 'active')
            <form method="POST" action="{{ route('admin.order.accept') }}">
              @csrf
              <input type="hidden" name="order_id" value="{{ $order->id }}">
              <button type="submit" class="btn btn-primary btn-sm w-100 text-start"><i class="fa-solid fa-check" style="font-size:11px"></i> Terima & Aktifkan</button>
            </form>
          @endif
          @if ($order->status !== 'pending')
            <form method="POST" action="{{ route('admin.order.mark.pending') }}">
              @csrf
              <input type="hidden" name="order_id" value="{{ $order->id }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm w-100 text-start"><i class="fa-solid fa-clock" style="font-size:11px"></i> Kembalikan ke Pending</button>
            </form>
          @endif
          @if ($order->status !== 'cancelled')
            <form method="POST" action="{{ route('admin.order.cancel') }}" data-confirm="Batalkan order ini?" data-confirm-title="Batalkan" data-confirm-style="warn" data-confirm-label="Ya, Batalkan">
              @csrf
              <input type="hidden" name="order_id" value="{{ $order->id }}">
              <button type="submit" class="btn btn-outline-danger btn-sm w-100 text-start"><i class="fa-solid fa-xmark" style="font-size:11px"></i> Batalkan Order</button>
            </form>
          @endif
          <a href="{{ route('admin.order.edit.page', $order) }}" class="btn btn-outline-secondary btn-sm w-100 text-start">
            <i class="fa-regular fa-pen-to-square" style="font-size:11px"></i> Edit Data Order
          </a>
        </div>
      </div>
    </div>
  </div>

@endsection
