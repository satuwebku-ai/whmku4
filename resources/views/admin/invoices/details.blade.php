@extends('layouts.admin')

@section('title', 'Detail Invoice ' . $invoice->invoice_number)

@section('content')

  @php $displayStatus = $invoice->is_overdue ? 'overdue' : $invoice->status; @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <a href="{{ route('admin.invoices') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Invoice</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $invoice->invoice_number }}</h1>
    </div>
    @php
      $badgeMap = ['paid' => 'badge-soft-success', 'unpaid' => 'badge-soft-warning', 'overdue' => 'badge-soft-danger', 'cancelled' => 'badge-soft-secondary'];
    @endphp
    <span class="badge {{ $badgeMap[$displayStatus] ?? 'badge-soft-secondary' }}" style="font-size:13px;padding:.4rem .8rem">
      {{ $displayStatus === 'overdue' ? 'Overdue' : ucfirst($displayStatus) }}
    </span>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">

      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-3">Rincian Invoice</h2>
        <div class="row g-3 small mb-3">
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">KLIEN</p>
            <p class="fw-medium text-dark mb-0">{{ $invoice->client->name ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">ORDER TERKAIT</p>
            <p class="fw-medium text-dark mb-0">
              @if ($invoice->order)
                <a href="{{ route('admin.orders.details', $invoice->order) }}" class="text-decoration-none text-accent">#{{ $invoice->order->order_number }}</a>
              @else
                —
              @endif
            </p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">TANGGAL TERBIT</p>
            <p class="fw-medium text-dark mb-0">{{ $invoice->issue_date->format('d M Y') }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">JATUH TEMPO</p>
            <p class="fw-medium text-dark mb-0">{{ $invoice->due_date->format('d M Y') }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">METODE PEMBAYARAN</p>
            <p class="fw-medium text-dark mb-0">{{ $invoice->payment_method ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">DIBAYAR PADA</p>
            <p class="fw-medium text-dark mb-0">{{ $invoice->paid_at?->format('d M Y') ?? '—' }}</p>
          </div>
        </div>

        <div class="border-top pt-3 small">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Subtotal</span><span class="text-dark">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Pajak</span><span class="text-dark">Rp {{ number_format($invoice->tax, 0, ',', '.') }}</span></div>
          <div class="d-flex justify-content-between fw-bold text-dark border-top pt-2" style="font-size:15px"><span>Total</span><span>Rp {{ number_format($invoice->total, 0, ',', '.') }}</span></div>
        </div>
      </div>

      @if ($invoice->items->isNotEmpty())
        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-2">Item Pesanan</h2>
          @foreach ($invoice->items as $lineItem)
            <div class="d-flex align-items-center justify-content-between py-2 small border-bottom">
              <div>
                <p class="text-dark mb-0">{{ $lineItem->description }}</p>
                @if ($lineItem->order)
                  <a href="{{ route('admin.orders.details', $lineItem->order) }}" class="text-decoration-none text-accent" style="font-size:11px">
                    #{{ $lineItem->order->order_number }} · {{ ucfirst($lineItem->order->status) }}
                  </a>
                @endif
              </div>
              <span class="fw-medium text-dark">Rp {{ number_format($lineItem->amount, 0, ',', '.') }}</span>
            </div>
          @endforeach
        </div>
      @endif

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Catatan</h2>
        <form method="POST" action="{{ route('admin.invoice.notes') }}">
          @csrf
          <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
          <textarea name="notes" rows="4" class="form-control form-control-sm" placeholder="Catatan tentang invoice ini...">{{ old('notes', $invoice->notes) }}</textarea>
          <button type="submit" class="btn btn-outline-secondary btn-sm mt-2"><i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Catatan</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Aksi</h2>
        <div class="d-flex flex-column gap-2">
          @if ($invoice->status !== 'paid')
            <form method="POST" action="{{ route('admin.invoice.mark.paid') }}">
              @csrf
              <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
              <button type="submit" class="btn btn-primary btn-sm w-100 text-start"><i class="fa-solid fa-check" style="font-size:11px"></i> Tandai Lunas</button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.invoice.mark.unpaid') }}">
              @csrf
              <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm w-100 text-start"><i class="fa-solid fa-rotate-left" style="font-size:11px"></i> Batalkan Status Lunas</button>
            </form>
          @endif
          @if ($invoice->status !== 'cancelled')
            <form method="POST" action="{{ route('admin.invoice.cancel') }}" data-confirm="Batalkan invoice ini?" data-confirm-title="Batalkan" data-confirm-style="warn" data-confirm-label="Ya, Batalkan">
              @csrf
              <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
              <button type="submit" class="btn btn-outline-danger btn-sm w-100 text-start"><i class="fa-solid fa-xmark" style="font-size:11px"></i> Batalkan Invoice</button>
            </form>
          @endif
          <a href="{{ route('admin.invoice.edit.page', $invoice) }}" class="btn btn-outline-secondary btn-sm w-100 text-start">
            <i class="fa-regular fa-pen-to-square" style="font-size:11px"></i> Edit Data Invoice
          </a>
        </div>
      </div>
    </div>
  </div>

@endsection
