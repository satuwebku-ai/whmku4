@extends('layouts.admin')

@section('title', $order->exists ? 'Edit Order' : 'Buat Order')

@section('content')

  @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $order->exists ? 'Edit Order' : 'Buat Order Baru' }}</h1>
    @if ($order->exists)
      <p class="small text-muted mb-0">Nomor order: <span class="fw-medium text-dark">#{{ $order->order_number }}</span></p>
    @else
      <p class="small text-muted mb-0">Nomor order akan dibuat otomatis.</p>
    @endif
  </div>

  <form method="POST" action="{{ $order->exists ? route('admin.order.update', $order) : route('admin.order.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($order->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Klien</label>
        <select name="client_id" class="form-select" style="{{ $selectStyle }}" required>
          <option value="">Pilih klien</option>
          @foreach ($clients as $client)
            <option value="{{ $client->id }}" @selected(old('client_id', $order->client_id) == $client->id)>{{ $client->name }}</option>
          @endforeach
        </select>
        @error('client_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Hosting Account Terkait (opsional)</label>
        <select name="hosting_account_id" class="form-select" style="{{ $selectStyle }}">
          <option value="">— Tidak terkait —</option>
          @foreach ($hostingAccounts as $ha)
            <option value="{{ $ha->id }}" @selected(old('hosting_account_id', $order->hosting_account_id) == $ha->id)>{{ $ha->domain }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama Produk</label>
        <input type="text" name="product_name" value="{{ old('product_name', $order->product_name) }}" placeholder="Cloud Hosting - Pro" class="form-control form-control-sm" required>
        @error('product_name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Tipe Order</label>
        <select name="order_type" class="form-select" style="{{ $selectStyle }}">
          <option value="hosting" @selected(old('order_type', $order->order_type) === 'hosting')>Hosting</option>
          <option value="domain" @selected(old('order_type', $order->order_type) === 'domain')>Domain</option>
          <option value="vps" @selected(old('order_type', $order->order_type) === 'vps')>VPS</option>
          <option value="other" @selected(old('order_type', $order->order_type) === 'other')>Lainnya</option>
        </select>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Jumlah (Rp)</label>
        <input type="number" step="0.01" name="amount" value="{{ old('amount', $order->amount) }}" class="form-control form-control-sm" required>
        @error('amount') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Status</label>
        <select name="status" class="form-select" style="{{ $selectStyle }}">
          <option value="pending" @selected(old('status', $order->status) === 'pending')>Pending</option>
          <option value="active" @selected(old('status', $order->status) === 'active')>Aktif</option>
          <option value="suspended" @selected(old('status', $order->status) === 'suspended')>Suspended</option>
          <option value="cancelled" @selected(old('status', $order->status) === 'cancelled')>Cancelled</option>
        </select>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.orders') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
