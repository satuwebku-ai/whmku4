@extends('layouts.admin')

@section('title', 'Buat Pembayaran')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Buat Pembayaran</h1>
    <p class="small text-muted mb-0">Pilih invoice yang belum lunas dan gateway yang dipakai. Untuk gateway otomatis, link pembayaran langsung dibuat.</p>
  </div>

  @if ($invoices->isEmpty())
    <div class="card border rounded-4 p-4 text-center text-muted small" style="max-width:42rem">
      Tidak ada invoice berstatus unpaid/overdue. Buat invoice dulu di menu Invoice.
    </div>
  @elseif ($gateways->isEmpty())
    <div class="card border rounded-4 p-4 text-center text-muted small" style="max-width:42rem">
      Belum ada payment gateway aktif. Tambahkan dulu di
      <a href="{{ route('admin.gateways') }}" class="text-accent">tab Gateway</a>.
    </div>
  @else
    <form method="POST" action="{{ route('admin.payment.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
      @csrf

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Invoice</label>
        <select name="invoice_id" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem" required>
          <option value="">Pilih invoice</option>
          @foreach ($invoices as $invoice)
            <option value="{{ $invoice->id }}" @selected(old('invoice_id') == $invoice->id)>
              {{ $invoice->invoice_number }} — {{ $invoice->client->name ?? '—' }} (Rp {{ number_format($invoice->total, 0, ',', '.') }})
            </option>
          @endforeach
        </select>
        @error('invoice_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Payment Gateway</label>
        <select name="payment_gateway_id" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem" required>
          <option value="">Pilih gateway</option>
          @foreach ($gateways as $gw)
            <option value="{{ $gw->id }}" @selected(old('payment_gateway_id') == $gw->id)>
              {{ $gw->name }} ({{ $gw->driver_label }}{{ $gw->isSandbox() && ! $gw->isManual() ? ' — Sandbox' : '' }})
            </option>
          @endforeach
        </select>
        @error('payment_gateway_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Biaya gateway (jika ada) otomatis ditambahkan ke total tagihan.</p>
      </div>

      <div class="d-flex align-items-center gap-2 pt-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Buat Pembayaran</button>
        <a href="{{ route('admin.payments') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
      </div>
    </form>
  @endif

@endsection
