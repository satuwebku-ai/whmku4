@extends('layouts.admin')

@section('title', 'Billing Dashboard')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h4 fw-bold text-dark mb-1">Billing Dashboard</h1>
    <p class="small text-muted mb-0">Ringkasan pendapatan, piutang, pembayaran, dan anomali billing.</p>
  </div>
  <a href="{{ route('admin.invoices') }}" class="btn btn-outline-secondary btn-sm">Kelola Invoice</a>
</div>

<form method="GET" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex align-items-end gap-2 flex-wrap">
    <div>
      <label class="form-label small fw-semibold">Dari</label>
      <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div>
      <label class="form-label small fw-semibold">Sampai</label>
      <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
    </div>
    <button class="btn btn-primary btn-sm">Terapkan</button>
    <a href="{{ route('admin.billing.dashboard') }}" class="btn btn-light btn-sm">Bulan Ini</a>
  </div>
</form>

<div class="row g-3 mb-4">
  @php
    $cards = [
      ['label'=>'Pendapatan Periode', 'value'=>'Rp '.number_format((float)$revenue,0,',','.'), 'icon'=>'fa-chart-line'],
      ['label'=>'Piutang Terbuka', 'value'=>'Rp '.number_format((float)$outstanding,0,',','.'), 'icon'=>'fa-file-invoice-dollar'],
      ['label'=>'Overdue', 'value'=>'Rp '.number_format((float)$overdue,0,',','.'), 'icon'=>'fa-triangle-exclamation'],
      ['label'=>'Pembayaran Hari Ini', 'value'=>'Rp '.number_format((float)$paymentsToday,0,',','.'), 'icon'=>'fa-money-bill-transfer'],
    ];
  @endphp
  @foreach($cards as $card)
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div><div class="small text-muted mb-2">{{ $card['label'] }}</div><div class="h5 fw-bold mb-0">{{ $card['value'] }}</div></div>
            <div class="rounded-3 bg-light p-2"><i class="fa-solid {{ $card['icon'] }} text-secondary"></i></div>
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-xl-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3"><h2 class="h6 fw-bold mb-0">Pendapatan Harian</h2><span class="small text-muted">{{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</span></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Tanggal</th><th class="text-end">Pendapatan</th></tr></thead><tbody>
          @forelse($dailyRevenue as $day)
            <tr><td>{{ $day['label'] }}</td><td class="text-end fw-semibold">Rp {{ number_format($day['value'],0,',','.') }}</td></tr>
          @empty
            <tr><td colspan="2" class="text-center text-muted py-4">Belum ada data.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Kontrol Billing</h2>
        <div class="d-flex justify-content-between py-2 border-bottom"><span class="small text-muted">Invoice lunas</span><strong>{{ number_format($paidCount) }}</strong></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span class="small text-muted">Top-up periode</span><strong>Rp {{ number_format((float)$topupRevenue,0,',','.') }}</strong></div>
        <div class="d-flex justify-content-between py-2"><span class="small text-muted">Anomali charge</span><strong class="{{ $reconciliationIssues['paid_invoice_without_charge'] ? 'text-danger' : 'text-success' }}">{{ $reconciliationIssues['paid_invoice_without_charge'] }}</strong></div>
        <div class="d-flex justify-content-between py-2"><span class="small text-muted">Payment mismatch</span><strong class="{{ $reconciliationIssues['paid_payment_without_invoice'] ? 'text-danger' : 'text-success' }}">{{ $reconciliationIssues['paid_payment_without_invoice'] }}</strong></div>
        <a href="{{ route('admin.invoices.overdue') }}" class="btn btn-outline-warning btn-sm w-100 mt-2">Lihat Invoice Overdue</a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3"><h2 class="h6 fw-bold mb-0">Pembayaran Terbaru</h2><a href="{{ route('admin.payments') }}" class="small">Lihat semua</a></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Reference</th><th>Klien</th><th>Invoice</th><th class="text-end">Total</th></tr></thead><tbody>
          @forelse($recentPayments as $payment)
            <tr><td>{{ $payment->reference }}</td><td>{{ $payment->client?->name ?? '—' }}</td><td>{{ $payment->invoice?->invoice_number ?? '—' }}</td><td class="text-end fw-semibold">Rp {{ number_format((float)$payment->total,0,',','.') }}</td></tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pembayaran.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Invoice Overdue Terlama</h2>
        @forelse($overdueInvoices as $invoice)
          <a href="{{ route('admin.invoices.details', $invoice) }}" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none">
            <div><div class="small fw-semibold text-dark">{{ $invoice->invoice_number }}</div><div class="small text-muted">{{ $invoice->client?->name ?? '—' }} · jatuh tempo {{ optional($invoice->due_date)->format('d M Y') }}</div></div>
            <span class="small fw-bold text-danger">Rp {{ number_format((float)$invoice->total,0,',','.') }}</span>
          </a>
        @empty
          <div class="text-muted small py-3">Tidak ada invoice overdue.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
