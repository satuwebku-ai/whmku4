@extends('layouts.admin')

@section('title', $invoice->exists ? 'Edit Invoice' : 'Buat Invoice')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $invoice->exists ? 'Edit Invoice' : 'Buat Invoice Manual' }}</h1>
    @if ($invoice->exists)
      <p class="small text-muted mb-0">No. Invoice: <span class="fw-medium text-dark">{{ $invoice->invoice_number }}</span></p>
    @else
      <p class="small text-muted mb-0">Nomor invoice akan dibuat otomatis (format INV-{{ date('Y') }}-xxxx).</p>
    @endif
  </div>

  <form method="POST" action="{{ $invoice->exists ? route('admin.invoice.update', $invoice) : route('admin.invoice.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($invoice->exists) @method('PUT') @endif

    @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Klien</label>
        <select name="client_id" class="form-select" style="{{ $selectStyle }}" required>
          <option value="">Pilih klien</option>
          @foreach ($clients as $client)
            <option value="{{ $client->id }}" @selected(old('client_id', $invoice->client_id) == $client->id)>{{ $client->name }}</option>
          @endforeach
        </select>
        @error('client_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Order Terkait (opsional)</label>
        <select name="order_id" class="form-select" style="{{ $selectStyle }}">
          <option value="">— Tidak terkait —</option>
          @foreach ($orders as $order)
            <option value="{{ $order->id }}" @selected(old('order_id', $invoice->order_id) == $order->id)>#{{ $order->order_number }} — {{ $order->product_name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Jumlah / Subtotal (Rp)</label>
        <input type="number" step="0.01" name="amount" value="{{ old('amount', $invoice->amount) }}" class="form-control form-control-sm" required>
        @error('amount') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Pajak (Rp, opsional)</label>
        <input type="number" step="0.01" name="tax" value="{{ old('tax', $invoice->tax ?? 0) }}" class="form-control form-control-sm">
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Tanggal Terbit</label>
        <input type="date" name="issue_date" value="{{ old('issue_date', optional($invoice->issue_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="form-control form-control-sm" required>
        @error('issue_date') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Jatuh Tempo</label>
        <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d') ?? now()->addDays(7)->format('Y-m-d')) }}" class="form-control form-control-sm" required>
        @error('due_date') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Status</label>
        <select name="status" class="form-select" style="{{ $selectStyle }}">
          <option value="unpaid" @selected(old('status', $invoice->status) === 'unpaid')>Unpaid</option>
          <option value="paid" @selected(old('status', $invoice->status) === 'paid')>Paid</option>
          <option value="overdue" @selected(old('status', $invoice->status) === 'overdue')>Overdue</option>
          <option value="cancelled" @selected(old('status', $invoice->status) === 'cancelled')>Cancelled</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Metode Pembayaran (opsional)</label>
        <input type="text" name="payment_method" value="{{ old('payment_method', $invoice->payment_method) }}" placeholder="Transfer Bank / Midtrans / dll" class="form-control form-control-sm">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Catatan (opsional)</label>
      <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $invoice->notes) }}</textarea>
    </div>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.invoices') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
