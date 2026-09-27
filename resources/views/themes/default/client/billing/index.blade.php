@extends('client.layout')
@section('title', 'Billing')

@section('content')
  @php
    $statusLabel = [
      'initiated' => 'Dimulai',
      'pending' => 'Menunggu',
      'paid' => 'Lunas',
      'failed' => 'Gagal',
      'expired' => 'Kedaluwarsa',
      'refunded' => 'Refund',
    ];
    $statusBadge = [
      'initiated' => 'badge-soft-warning',
      'pending' => 'badge-soft-warning',
      'paid' => 'badge-soft-success',
      'failed' => 'badge-soft-danger',
      'expired' => 'badge-soft-secondary',
      'refunded' => 'badge-soft-secondary',
    ];
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Billing</h1>
      <p class="text-muted mb-0">Ringkasan tagihan, pembayaran, dan saldo akun Anda.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('client.balance') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-wallet" style="font-size:11px"></i> Saldo Saya
      </a>
      <a href="{{ route('client.invoices') }}" class="btn btn-theme btn-sm">
        <i class="fa-solid fa-file-invoice" style="font-size:11px"></i> Semua Invoice
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
      <div class="dash-card p-4 h-100">
        <p class="text-muted mb-1" style="font-size:11px">Tagihan Terbuka</p>
        <p class="h4 fw-bold text-dark mb-1">{{ $summary['unpaid_count'] }}</p>
        <p class="text-muted mb-0" style="font-size:11px">Rp {{ number_format($summary['unpaid_total'], 0, ',', '.') }}</p>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="dash-card p-4 h-100">
        <p class="text-muted mb-1" style="font-size:11px">Total Invoice Lunas</p>
        <p class="h4 fw-bold text-success mb-1">Rp {{ number_format($summary['paid_total'], 0, ',', '.') }}</p>
        <p class="text-muted mb-0" style="font-size:11px">Sepanjang akun</p>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="dash-card p-4 h-100">
        <p class="text-muted mb-1" style="font-size:11px">Saldo Akun</p>
        <p class="h4 fw-bold text-dark mb-1">Rp {{ number_format($summary['balance'], 0, ',', '.') }}</p>
        <a href="{{ route('client.balance') }}" class="text-decoration-none" style="font-size:11px">Kelola saldo &rarr;</a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="dash-card p-4 h-100">
        <p class="text-muted mb-1" style="font-size:11px">Pembayaran Terakhir</p>
        @php $latestPaid = $recentPayments->firstWhere('status', 'paid'); @endphp
        <p class="h6 fw-bold text-dark mb-1">{{ $latestPaid?->paid_at?->format('d M Y') ?? '—' }}</p>
        <p class="text-muted mb-0" style="font-size:11px">{{ $latestPaid?->payment_method ?? $latestPaid?->gateway?->name ?? 'Belum ada' }}</p>
      </div>
    </div>
  </div>

  @if ($unpaidInvoices->isNotEmpty())
    <div class="dash-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="h6 fw-bold text-dark mb-1">Perlu Dibayar</h2>
          <p class="text-muted mb-0" style="font-size:11px">Invoice yang masih terbuka atau sudah melewati jatuh tempo.</p>
        </div>
      </div>
      <div class="d-flex flex-column gap-2">
        @foreach ($unpaidInvoices as $invoice)
          <div class="d-flex align-items-center justify-content-between gap-3 p-3 rounded-3" style="background:#f8fafc">
            <div class="min-w-0">
              <a href="{{ route('client.invoices.show', $invoice) }}" class="fw-semibold text-dark text-decoration-none">{{ $invoice->invoice_number }}</a>
              <div class="text-muted" style="font-size:11px">
                Jatuh tempo {{ $invoice->due_date?->format('d M Y') ?? '—' }}
                @if ($invoice->is_overdue) · <span class="text-danger fw-semibold">Terlambat</span> @endif
              </div>
            </div>
            <div class="text-end flex-shrink-0">
              <div class="fw-bold text-dark" style="font-size:14px">Rp {{ number_format($invoice->total, 0, ',', '.') }}</div>
              <a href="{{ route('client.invoices.show', $invoice) }}" class="btn btn-theme btn-sm mt-1">Bayar</a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-xl-7">
      <div class="dash-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 fw-bold text-dark mb-0">Invoice Terbaru</h2>
          <a href="{{ route('client.invoices') }}" class="text-decoration-none" style="font-size:11px">Lihat semua</a>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr class="small text-muted"><th>Invoice</th><th>Terbit</th><th class="text-end">Total</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($recentInvoices as $invoice)
                <tr>
                  <td><a href="{{ route('client.invoices.show', $invoice) }}" class="fw-semibold text-dark text-decoration-none">{{ $invoice->invoice_number }}</a></td>
                  <td class="text-muted" style="font-size:12px">{{ $invoice->issue_date?->format('d M Y') }}</td>
                  <td class="text-end fw-semibold">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                  <td><span class="badge {{ $invoice->is_overdue ? 'badge-soft-danger' : match($invoice->status) { 'paid' => 'badge-soft-success', 'unpaid' => 'badge-soft-warning', 'overdue' => 'badge-soft-danger', default => 'badge-soft-secondary' } }}">{{ $invoice->is_overdue ? 'Terlambat' : ucfirst($invoice->status) }}</span></td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada invoice.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="dash-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 fw-bold text-dark mb-0">Riwayat Pembayaran</h2>
        </div>
        <div class="d-flex flex-column gap-2">
          @forelse ($recentPayments as $payment)
            <div class="d-flex align-items-center gap-3 py-2 border-bottom">
              <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:34px;height:34px;background:#f1f5f9">
                <i class="fa-solid fa-credit-card text-muted" style="font-size:12px"></i>
              </span>
              <div class="min-w-0 flex-grow-1">
                <a href="{{ $payment->invoice ? route('client.invoices.show', $payment->invoice) : route('client.billing') }}" class="fw-semibold text-dark text-decoration-none" style="font-size:12px">{{ $payment->invoice?->invoice_number ?? $payment->reference }}</a>
                <div class="text-muted" style="font-size:10px">{{ $payment->gateway?->name ?? $payment->payment_method ?? 'Pembayaran' }} · {{ $payment->created_at?->format('d M Y H:i') }}</div>
              </div>
              <div class="text-end flex-shrink-0">
                <div class="fw-semibold text-dark" style="font-size:12px">Rp {{ number_format($payment->total, 0, ',', '.') }}</div>
                <span class="badge {{ $statusBadge[$payment->status] ?? 'badge-soft-secondary' }}" style="font-size:9px">{{ $statusLabel[$payment->status] ?? ucfirst($payment->status) }}</span>
              </div>
            </div>
          @empty
            <p class="text-muted text-center py-4 mb-0" style="font-size:12px">Belum ada riwayat pembayaran.</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
