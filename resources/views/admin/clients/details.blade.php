@extends('layouts.admin')

@section('title', 'Profil Klien — ' . $client->name)

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <a href="{{ route('admin.clients') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Klien</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $client->name }}</h1>
      @if ($client->company)
        <p class="small text-muted mb-0">{{ $client->company }}</p>
      @endif
    </div>
    <span class="badge {{ $client->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}" style="font-size:13px;padding:.4rem .8rem">
      {{ $client->status === 'active' ? 'Aktif' : 'Nonaktif' }}
    </span>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-4">
      <div class="card border rounded-4 p-3 text-center">
        <p class="h4 fw-bold text-dark mb-0">{{ $client->hosting_accounts_count }}</p>
        <p class="text-muted mb-0" style="font-size:12px">Hosting Account</p>
      </div>
    </div>
    <div class="col-4">
      <div class="card border rounded-4 p-3 text-center">
        <p class="h4 fw-bold text-dark mb-0">{{ $client->orders_count }}</p>
        <p class="text-muted mb-0" style="font-size:12px">Order</p>
      </div>
    </div>
    <div class="col-4">
      <div class="card border rounded-4 p-3 text-center">
        <p class="h4 fw-bold text-dark mb-0">{{ $client->invoices_count }}</p>
        <p class="text-muted mb-0" style="font-size:12px">Invoice</p>
      </div>
    </div>
  </div>

  @php
    $statusBadge = fn ($s) => match ($s) {
        'active', 'paid' => 'badge-soft-success',
        'pending', 'unpaid' => 'badge-soft-warning',
        'suspended', 'overdue' => 'badge-soft-danger',
        default => 'badge-soft-secondary',
    };
  @endphp

  <div class="row g-3">
    <div class="col-12 col-lg-8">

      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-3">Informasi Kontak</h2>
        <div class="row g-3 small">
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">EMAIL</p>
            <p class="fw-medium text-dark mb-0">{{ $client->email }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">TELEPON</p>
            <p class="fw-medium text-dark mb-0">{{ $client->phone ?? '—' }}</p>
          </div>
          <div class="col-12">
            <p class="text-muted mb-1" style="font-size:11px">ALAMAT</p>
            <p class="fw-medium text-dark mb-0">{{ $client->address ?? '—' }}, {{ $client->city }}, {{ $client->country }}</p>
          </div>
        </div>
      </div>

      @if ($client->orders->isNotEmpty())
        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-2">Order Terbaru</h2>
          @foreach ($client->orders as $order)
            <a href="{{ route('admin.orders.details', $order) }}" class="d-flex align-items-center justify-content-between py-2 small text-decoration-none border-bottom text-dark">
              <span>#{{ $order->order_number }} — {{ $order->product_name }}</span>
              <span class="badge {{ $statusBadge($order->status) }}">{{ ucfirst($order->status) }}</span>
            </a>
          @endforeach
        </div>
      @endif

      @if ($client->invoices->isNotEmpty())
        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-2">Invoice Terbaru</h2>
          @foreach ($client->invoices as $invoice)
            <a href="{{ route('admin.invoices.details', $invoice) }}" class="d-flex align-items-center justify-content-between py-2 small text-decoration-none border-bottom text-dark">
              <span>{{ $invoice->invoice_number }}</span>
              <span class="badge {{ $statusBadge($invoice->status) }}">{{ ucfirst($invoice->status) }}</span>
            </a>
          @endforeach
        </div>
      @endif

      @if ($client->hostingAccounts->isNotEmpty())
        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-2">Hosting Account</h2>
          @foreach ($client->hostingAccounts as $account)
            <a href="{{ route('admin.hosting-accounts.details', $account) }}" class="d-flex align-items-center justify-content-between py-2 small text-decoration-none border-bottom text-dark">
              <span>{{ $account->domain }}</span>
              <span class="badge {{ $statusBadge($account->status) }}">{{ ucfirst($account->status) }}</span>
            </a>
          @endforeach
        </div>
      @endif

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Catatan Internal</h2>
        <form method="POST" action="{{ route('admin.client.notes') }}">
          @csrf
          <input type="hidden" name="client_id" value="{{ $client->id }}">
          <textarea name="internal_notes" rows="4" class="form-control form-control-sm" placeholder="Catatan staf tentang klien ini...">{{ old('internal_notes', $client->internal_notes) }}</textarea>
          <button type="submit" class="btn btn-outline-secondary btn-sm mt-2"><i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Catatan</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-1">Saldo Klien</h2>
        <p class="h4 fw-bold text-dark mb-3">Rp {{ number_format((float) $client->balance, 0, ',', '.') }}</p>

        <form method="POST" action="{{ route('admin.client.balance.adjust', $client) }}" class="mb-3">
          @csrf
          <input type="number" name="amount" step="1000" placeholder="Nominal (- untuk kurangi)" required
                 class="form-control form-control-sm mb-2">
          <input type="text" name="description" placeholder="Alasan (mis. Refund invoice INV-2026-0003)" required
                 class="form-control form-control-sm mb-2">
          @error('amount') <p class="text-danger mb-1" style="font-size:11px">{{ $message }}</p> @enderror
          @error('description') <p class="text-danger mb-1" style="font-size:11px">{{ $message }}</p> @enderror
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
            <i class="fa-solid fa-sliders" style="font-size:11px"></i> Sesuaikan Saldo
          </button>
        </form>

        @if ($client->balanceLogs->isNotEmpty())
          <div class="border-top pt-2" style="max-height:14rem;overflow-y:auto">
            @foreach ($client->balanceLogs as $log)
              <div class="d-flex align-items-center justify-content-between mb-2" style="font-size:12px">
                <div class="min-w-0">
                  <p class="text-dark mb-0 text-truncate">{{ $log->description }}</p>
                  <p class="text-muted mb-0">{{ $log->created_at->format('d M Y H:i') }}</p>
                </div>
                <span class="fw-medium flex-shrink-0 ms-2 {{ $log->amount >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ $log->amount >= 0 ? '+' : '' }}{{ number_format($log->amount, 0, ',', '.') }}
                </span>
              </div>
            @endforeach
          </div>
        @else
          <p class="text-muted border-top pt-2 mb-0" style="font-size:12px">Belum ada riwayat saldo.</p>
        @endif
      </div>

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Aksi</h2>
        <div class="d-flex flex-column gap-2">
          <form method="POST" action="{{ route('admin.client.status') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <button type="submit" class="btn {{ $client->status === 'active' ? 'btn-outline-danger' : 'btn-primary' }} btn-sm w-100 text-start">
              <i class="fa-solid {{ $client->status === 'active' ? 'fa-user-slash' : 'fa-user-check' }}" style="font-size:11px"></i>
              {{ $client->status === 'active' ? 'Nonaktifkan Klien' : 'Aktifkan Klien' }}
            </button>
          </form>
          <a href="{{ route('admin.client.edit.page', $client) }}" class="btn btn-outline-secondary btn-sm w-100 text-start">
            <i class="fa-regular fa-pen-to-square" style="font-size:11px"></i> Edit Data Klien
          </a>

          @if (auth('admin')->user()->hasModule('services'))
            <form method="POST" action="{{ route('admin.client.impersonate', $client) }}"
                  data-confirm="Anda akan masuk ke akun {{ $client->name }} ({{ $client->email }}). Aktivitas ini tercatat di log Aktivitas. Lanjutkan?"
                  data-confirm-title="Login sebagai Klien" data-confirm-style="warn" data-confirm-label="Ya, Masuk">
              @csrf
              <button type="submit" class="btn btn-outline-warning btn-sm w-100 text-start">
                <i class="fa-solid fa-user-shield" style="font-size:11px"></i> Login sebagai Klien Ini
              </button>
            </form>
            <p class="text-muted mb-0" style="font-size:11px">
              Berguna untuk troubleshooting. Tercatat di menu Aktivitas dan Percobaan Login.
            </p>
          @endif
        </div>
      </div>
    </div>
  </div>

@endsection
