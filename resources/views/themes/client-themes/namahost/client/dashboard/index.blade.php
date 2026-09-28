@extends('client.layout')

@section('title', 'Dashboard')

@section('content')

  @php
    $badgeMap = [
      'active' => 'badge-soft-success', 'paid' => 'badge-soft-success',
      'pending' => 'badge-soft-warning', 'unpaid' => 'badge-soft-warning', 'answered' => 'badge-soft-warning',
      'suspended' => 'badge-soft-danger', 'overdue' => 'badge-soft-danger', 'expired' => 'badge-soft-danger',
      'inactive' => 'badge-soft-secondary', 'closed' => 'badge-soft-secondary', 'cancelled' => 'badge-soft-secondary', 'terminated' => 'badge-soft-secondary',
    ];
  @endphp

  {{-- Banner sapaan --}}
  <div class="welcome d-flex flex-wrap justify-content-between align-items-center p-4 mb-4 gap-2">
    <div>
      <h1 class="h3 mb-0">Halo, {{ $client->name }} <span class="wave">&#128075;</span></h1>
      <small class="text-white-50">Berikut ringkasan layanan Anda hari ini.</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('client.tickets.create') }}" class="btn btn-outline-light">Buka tiket</a>
      <a href="{{ route('catalog.index') }}" class="btn btn-accent">Tambah layanan</a>
    </div>
  </div>

  {{-- Tagihan menunggak --}}
  @if ($stats['unpaidInvoices'] > 0)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
      <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
          Anda punya <b>{{ $stats['unpaidInvoices'] }}</b> invoice belum dibayar dengan total
          <b>Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</b>.
        </div>
      </div>
      <a href="{{ route('client.invoices', ['status' => 'unpaid']) }}" class="btn btn-sm btn-primary">Bayar sekarang</a>
    </div>
  @endif

  {{-- Statistik --}}
  @php
    $cards = [
      ['label' => 'Layanan aktif',       'value' => $stats['services'],        'icon' => 'bi-server',    'tile' => 't-teal',   'color' => '#0e7c86', 'route' => 'client.services'],
      ['label' => 'Domain aktif',        'value' => $stats['domains'],         'icon' => 'bi-globe2',    'tile' => 't-indigo', 'color' => '#5b5bd6', 'route' => 'client.domains'],
      ['label' => 'Invoice belum bayar', 'value' => $stats['unpaidInvoices'],  'icon' => 'bi-receipt',   'tile' => 't-coral',  'color' => '#ff6f59', 'route' => 'client.invoices'],
      ['label' => 'Tiket terbuka',       'value' => $stats['openTickets'],     'icon' => 'bi-life-preserver', 'tile' => 't-amber', 'color' => '#f5a524', 'route' => 'client.tickets'],
    ];
  @endphp
  <div class="row g-3 mb-4">
    @foreach ($cards as $card)
      <div class="col-6 col-xl-3">
        <a href="{{ route($card['route']) }}" class="dash-card dash-card-hover stat-card d-block h-100 text-decoration-none" style="--stat-color:{{ $card['color'] }}">
          <div class="p-3 d-flex align-items-center gap-3">
            <span class="tile {{ $card['tile'] }}"><i class="bi {{ $card['icon'] }}"></i></span>
            <div>
              <small class="text-body-secondary">{{ $card['label'] }}</small>
              <div class="fs-3 fw-bold text-body" style="line-height:1.1">{{ $card['value'] }}</div>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-4">

    <div class="col-xl-8 d-flex flex-column gap-4">

      {{-- Invoice terbaru --}}
      <div class="card">
        <div class="card-header d-flex justify-content-between"><b>Invoice terbaru</b><a href="{{ route('client.invoices') }}" class="text-decoration-none">Lihat semua</a></div>

        @if ($recentInvoices->isEmpty())
          <div class="text-center py-5">
            <i class="bi bi-receipt fs-2 text-body-secondary"></i>
            <p class="text-body-secondary mb-0 mt-2">Belum ada invoice.</p>
          </div>
        @else
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>No. invoice</th><th>Jatuh tempo</th><th>Total</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @foreach ($recentInvoices as $invoice)
                  <tr>
                    <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->due_date->format('d M Y') }}</td>
                    <td>Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                    <td>
                      <span class="badge {{ $badgeMap[$invoice->is_overdue ? 'overdue' : $invoice->status] ?? 'badge-soft-secondary' }}">
                        {{ $invoice->is_overdue ? 'Terlambat' : ucfirst($invoice->status) }}
                      </span>
                    </td>
                    <td class="text-end">
                      <a href="{{ route('client.invoices.show', $invoice) }}" class="btn btn-sm {{ $invoice->status === 'unpaid' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $invoice->status === 'unpaid' ? 'Bayar' : 'Lihat' }}
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      {{-- Domain segera habis --}}
      @if ($expiringSoon->isNotEmpty())
        <div class="card">
          <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-warning"></i>
            <b>Domain akan segera kedaluwarsa</b>
          </div>
          <ul class="list-group list-group-flush">
            @foreach ($expiringSoon as $domain)
              <li class="list-group-item list-row d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold"><i class="bi bi-globe2 me-2 text-primary"></i>{{ $domain->domain_name }}</span>
                <span class="d-flex align-items-center gap-3">
                  <small class="text-warning-emphasis">
                    {{ $domain->expiry_date->format('d M Y') }}
                    ({{ (int) now()->diffInDays($domain->expiry_date) }} hari lagi)
                  </small>
                  <a href="{{ route('client.domains.show', $domain) }}" class="btn btn-sm btn-accent">Perpanjang</a>
                </span>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>

    <div class="col-xl-4 d-flex flex-column gap-4">

      {{-- Pengumuman --}}
      <div class="card">
        <div class="card-header fw-bold">Pengumuman</div>
        @if ($announcements->isEmpty())
          <div class="card-body text-body-secondary">Belum ada pengumuman.</div>
        @else
          <ul class="list-group list-group-flush">
            @foreach ($announcements as $item)
              <li class="list-group-item">
                <a href="{{ route('announcements.show', $item->slug) }}" target="_blank" class="text-decoration-none d-block">
                  <div class="d-flex justify-content-between gap-2">
                    <span class="text-body fw-semibold">{{ $item->title }}</span>
                    <span class="badge {{ $badgeMap[$item->category] ?? 'badge-soft-secondary' }} text-capitalize align-self-start">{{ $item->category }}</span>
                  </div>
                  <small class="text-body-secondary">{{ $item->published_at?->diffForHumans() }}</small>
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Bantuan --}}
      <div class="card">
        <div class="card-body">
          <span class="tile t-amber mb-3"><i class="bi bi-headset"></i></span>
          <h2 class="h6 fw-bold mb-2">Butuh bantuan?</h2>
          <p class="text-body-secondary mb-3">Tim support kami siap membantu masalah teknis maupun tagihan.</p>
          <a href="{{ route('client.tickets.create') }}" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Buat tiket support</a>
        </div>
      </div>
    </div>
  </div>

@endsection
