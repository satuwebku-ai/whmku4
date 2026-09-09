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

  <div class="d-flex align-items-center gap-3 mb-4">
    <span class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;background:linear-gradient(135deg,var(--lumora-theme),#818cf8);box-shadow:0 4px 12px -3px rgba(79,70,229,.45)">
      <span style="font-size:22px">👋</span>
    </span>
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Halo, {{ $client->name }}</h1>
      <p class="text-muted mb-0" style="font-size:14px">Berikut ringkasan layanan Anda hari ini.</p>
    </div>
  </div>

  {{-- Aksi cepat --}}
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('catalog.index') }}" class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none fw-medium" style="font-size:13px;background:#fff;border:1px solid #e6e9f7;color:#334155;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--lumora-theme)';this.style.color='var(--lumora-theme)'" onmouseout="this.style.borderColor='#e6e9f7';this.style.color='#334155'">
      <i class="fa-solid fa-cart-plus" style="font-size:12px;color:var(--lumora-theme)"></i> Pesan Layanan Baru
    </a>
    <a href="{{ route('client.balance') }}" class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none fw-medium" style="font-size:13px;background:#fff;border:1px solid #e6e9f7;color:#334155;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--lumora-theme)';this.style.color='var(--lumora-theme)'" onmouseout="this.style.borderColor='#e6e9f7';this.style.color='#334155'">
      <i class="fa-solid fa-wallet" style="font-size:12px;color:var(--lumora-theme)"></i> Isi Saldo
    </a>
    <a href="{{ route('client.tickets.create') }}" class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none fw-medium" style="font-size:13px;background:#fff;border:1px solid #e6e9f7;color:#334155;transition:border-color .15s,color .15s" onmouseover="this.style.borderColor='var(--lumora-theme)';this.style.color='var(--lumora-theme)'" onmouseout="this.style.borderColor='#e6e9f7';this.style.color='#334155'">
      <i class="fa-solid fa-headset" style="font-size:12px;color:var(--lumora-theme)"></i> Buat Tiket Support
    </a>
  </div>

  {{-- Tagihan menunggak tampil paling menonjol --}}
  @if ($stats['unpaidInvoices'] > 0)
    <div class="dash-card p-4 mb-4" style="border-color:#fde68a;background:linear-gradient(120deg,#fffbeb 0%,#fef3c7 100%)">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
          <span class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0 position-relative" style="width:44px;height:44px;background:rgba(180,83,9,.12);color:#b45309">
            <i class="fa-solid fa-circle-exclamation" style="font-size:18px"></i>
            <span class="position-absolute rounded-circle" style="top:-2px;right:-2px;width:10px;height:10px;background:#f59e0b;border:2px solid #fef3c7;animation:pulseDot 1.6s ease-out infinite"></span>
          </span>
          <div>
            <p class="fw-semibold mb-0" style="font-size:13px;color:#92400e">
              Anda punya {{ $stats['unpaidInvoices'] }} invoice belum dibayar
            </p>
            <p class="fw-bold text-dark mt-1 mb-0" style="font-size:1.6rem">Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</p>
          </div>
        </div>
        <a href="{{ route('client.invoices', ['status' => 'unpaid']) }}" class="btn btn-theme">
          <i class="fa-solid fa-credit-card" style="font-size:12px"></i> Bayar Sekarang
        </a>
      </div>
    </div>
  @endif

  {{-- Statistik --}}
  <div class="row g-3 mb-4">
    @php
      $cards = [
        ['label' => 'Layanan Aktif', 'value' => $stats['services'], 'icon' => 'fa-server', 'bg' => 'rgba(79,70,229,.1)', 'fg' => '#4f46e5', 'route' => 'client.services'],
        ['label' => 'Domain Aktif', 'value' => $stats['domains'], 'icon' => 'fa-globe', 'bg' => 'rgba(6,182,212,.1)', 'fg' => '#0891b2', 'route' => 'client.domains'],
        ['label' => 'Invoice Belum Bayar', 'value' => $stats['unpaidInvoices'], 'icon' => 'fa-file-invoice', 'bg' => 'rgba(245,158,11,.1)', 'fg' => '#b45309', 'route' => 'client.invoices'],
        ['label' => 'Tiket Terbuka', 'value' => $stats['openTickets'], 'icon' => 'fa-comments', 'bg' => 'rgba(16,185,129,.1)', 'fg' => '#047857', 'route' => 'client.tickets'],
      ];
    @endphp

    @foreach ($cards as $card)
      <div class="col-6 col-lg-3">
        <a href="{{ route($card['route']) }}" class="dash-card dash-card-hover stat-card p-4 text-decoration-none d-block h-100" style="--stat-color:{{ $card['fg'] }}">
          <span class="stat-icon mb-3" style="background:{{ $card['bg'] }};color:{{ $card['fg'] }}">
            <i class="fa-solid {{ $card['icon'] }}"></i>
          </span>
          <p class="fw-bold text-dark mb-0" style="font-size:1.6rem">{{ $card['value'] }}</p>
          <p class="text-muted mb-0 mt-1" style="font-size:12px">{{ $card['label'] }}</p>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-4">

    <div class="col-12 col-lg-8 d-flex flex-column gap-4">
      {{-- Invoice terbaru --}}
      <div class="dash-card overflow-hidden">
        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
          <h2 class="small fw-bold text-dark mb-0">Invoice Terbaru</h2>
          <a href="{{ route('client.invoices') }}" class="text-decoration-none text-theme fw-medium" style="font-size:12px">Lihat semua <i class="fa-solid fa-arrow-right" style="font-size:10px"></i></a>
        </div>

        @if ($recentInvoices->isEmpty())
          <div class="text-center py-5">
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:44px;height:44px;background:#f1f5f9;color:#94a3b8">
              <i class="fa-solid fa-file-invoice"></i>
            </span>
            <p class="text-muted mb-0" style="font-size:14px">Belum ada invoice.</p>
          </div>
        @else
          <div>
            @foreach ($recentInvoices as $invoice)
              <a href="{{ route('client.invoices.show', $invoice) }}" class="list-row d-flex align-items-center justify-content-between px-4 py-3 border-bottom text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                  <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:#f1f5f9;color:#64748b">
                    <i class="fa-solid fa-file-invoice" style="font-size:13px"></i>
                  </span>
                  <div>
                    <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $invoice->invoice_number }}</p>
                    <p class="text-muted mb-0" style="font-size:11px">Jatuh tempo {{ $invoice->due_date->format('d M Y') }}</p>
                  </div>
                </div>
                <div class="text-end">
                  <p class="fw-semibold text-dark mb-0" style="font-size:14px">Rp {{ number_format($invoice->total, 0, ',', '.') }}</p>
                  <span class="badge {{ $badgeMap[$invoice->is_overdue ? 'overdue' : $invoice->status] ?? 'badge-soft-secondary' }}">
                    {{ $invoice->is_overdue ? 'Terlambat' : ucfirst($invoice->status) }}
                  </span>
                </div>
              </a>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Domain segera habis --}}
      @if ($expiringSoon->isNotEmpty())
        <div class="dash-card overflow-hidden" style="border-color:#fde68a">
          <div class="px-4 py-3 border-bottom d-flex align-items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-warning" style="font-size:13px"></i>
            <h2 class="small fw-bold text-dark mb-0">Domain Akan Segera Kedaluwarsa</h2>
          </div>
          <div>
            @foreach ($expiringSoon as $domain)
              <a href="{{ route('client.domains.show', $domain) }}" class="list-row d-flex align-items-center justify-content-between px-4 py-3 border-bottom text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                  <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:rgba(180,83,9,.1);color:#b45309">
                    <i class="fa-solid fa-globe" style="font-size:13px"></i>
                  </span>
                  <span class="fw-medium text-dark" style="font-size:14px">{{ $domain->domain_name }}</span>
                </div>
                <span class="fw-medium" style="font-size:12px;color:#b45309">
                  {{ $domain->expiry_date->format('d M Y') }}
                  ({{ (int) now()->diffInDays($domain->expiry_date) }} hari lagi)
                </span>
              </a>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- Pengumuman --}}
    <div class="col-12 col-lg-4 d-flex flex-column gap-4">
      <div class="dash-card p-4">
        <h2 class="small fw-bold text-dark mb-3">Pengumuman</h2>
        @if ($announcements->isEmpty())
          <p class="text-muted mb-0" style="font-size:14px">Belum ada pengumuman.</p>
        @else
          <div class="d-flex flex-column gap-3">
            @foreach ($announcements as $item)
              <a href="{{ route('announcements.show', $item->slug) }}" target="_blank" class="text-decoration-none">
                <span class="badge {{ $badgeMap[$item->category] ?? 'badge-soft-secondary' }} text-capitalize mb-1 d-inline-block">{{ $item->category }}</span>
                <p class="fw-medium text-dark mb-0" style="font-size:14px;line-height:1.4">{{ $item->title }}</p>
                <p class="text-muted mb-0 mt-1" style="font-size:11px">{{ $item->published_at?->diffForHumans() }}</p>
              </a>
            @endforeach
          </div>
        @endif
      </div>

      <div class="dash-card p-4" style="background:linear-gradient(160deg,#eef2ff 0%,#fff 70%)">
        <span class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width:40px;height:40px;background:rgba(79,70,229,.12);color:var(--lumora-theme)">
          <i class="fa-solid fa-headset" style="font-size:15px"></i>
        </span>
        <h2 class="small fw-bold text-dark mb-2">Butuh Bantuan?</h2>
        <p class="text-muted mb-3" style="font-size:14px">Tim support kami siap membantu masalah teknis maupun tagihan.</p>
        <a href="{{ route('client.tickets.create') }}" class="btn btn-theme w-100">
          <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Tiket Support
        </a>
      </div>
    </div>
  </div>

@endsection
