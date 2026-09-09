@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Selamat datang, {{ auth('admin')->user()->name }} 👋</h1>
    <p class="small text-muted mb-0">Ringkasan aktivitas hosting &amp; billing hari ini.</p>
  </div>

  {{-- Stat widgets --}}
  <div class="row g-3 mb-4">
    @php
      $iconMap = [
        'users'     => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        'server'    => 'M4 4h16v6H4zM4 14h16v6H4zM8 8h.01M8 18h.01',
        'clipboard' => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
        'wallet'    => 'M2 7h20v10H2zM2 10h20M6 15h4',
      ];
      // Warna soft yang benar-benar ada di lumora-admin.css (badge-soft-*),
      // dipakai ulang di sini untuk latar ikon supaya tidak perlu warna baru.
      $colorMap = [
        'users'     => ['bg' => 'rgba(79,70,229,.12)', 'fg' => '#4338ca'],
        'server'    => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857'],
        'clipboard' => ['bg' => 'rgba(245,158,11,.16)', 'fg' => '#b45309'],
        'wallet'    => ['bg' => 'rgba(139,92,246,.14)', 'fg' => '#7c3aed'],
      ];
    @endphp

    @foreach ($stats as $stat)
      @php $c = $colorMap[$stat['icon']]; @endphp
      <div class="col-6 col-md-6 col-lg-3">
        <div class="card border rounded-4 p-4 h-100">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;background:{{ $c['bg'] }};color:{{ $c['fg'] }}">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $iconMap[$stat['icon']] }}"/></svg>
            </span>
            <span class="badge {{ $stat['trend'] === 'up' ? 'badge-soft-success' : 'badge-soft-danger' }}" style="font-size:11px">
              {{ $stat['delta'] }}
            </span>
          </div>
          <p class="h4 fw-bold text-dark mb-0">{{ $stat['value'] }}</p>
          <p class="small text-muted mb-0 mt-1">{{ $stat['label'] }}</p>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3">
    {{-- Recent orders --}}
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 overflow-hidden h-100">
        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
          <h2 class="small fw-bold text-dark mb-0">Order Terbaru</h2>
          <a href="#" class="small fw-medium text-accent text-decoration-none">Lihat semua</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th class="px-4">ID</th>
                <th>Klien</th>
                <th>Produk</th>
                <th>Status</th>
                <th class="text-end px-4">Total</th>
              </tr>
            </thead>
            <tbody>
              @php
                // Peta status Lumora -> warna badge-soft-* yang tersedia
                // di lumora-admin.css (bukan class baru).
                $statusBadge = [
                    'active' => 'badge-soft-success', 'paid' => 'badge-soft-success',
                    'pending' => 'badge-soft-warning', 'unpaid' => 'badge-soft-warning',
                    'suspended' => 'badge-soft-danger', 'overdue' => 'badge-soft-danger',
                    'terminated' => 'badge-soft-secondary', 'cancelled' => 'badge-soft-secondary',
                ];
              @endphp
              @foreach ($recentOrders as $order)
                <tr>
                  <td class="px-4 fw-medium text-dark">{{ $order['id'] }}</td>
                  <td class="text-muted">{{ $order['client'] }}</td>
                  <td class="text-muted">{{ $order['product'] }}</td>
                  <td>
                    <span class="badge {{ $statusBadge[$order['status']] ?? 'badge-soft-secondary' }}">
                      {{ ucfirst($order['status']) }}
                    </span>
                  </td>
                  <td class="text-end px-4 fw-medium text-dark">{{ $order['total'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Quick actions --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3">Aksi Cepat</h2>
        <div class="d-flex flex-column gap-2">
          @php
            $quickActions = [
              ['route' => 'admin.client.add.page', 'icon' => 'fa-user-plus', 'bg' => 'rgba(79,70,229,.12)', 'fg' => '#4338ca', 'label' => 'Tambah Klien Baru'],
              ['route' => 'admin.hosting-account.add.page', 'icon' => 'fa-server', 'bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857', 'label' => 'Buat Hosting Account'],
              ['route' => 'admin.invoice.add.page', 'icon' => 'fa-file-invoice', 'bg' => 'rgba(245,158,11,.16)', 'fg' => '#b45309', 'label' => 'Buat Invoice Manual'],
              ['route' => 'admin.order.add.page', 'icon' => 'fa-cart-plus', 'bg' => 'rgba(139,92,246,.14)', 'fg' => '#7c3aed', 'label' => 'Buat Order'],
              ['route' => 'admin.domain.search', 'icon' => 'fa-globe', 'bg' => 'rgba(14,165,233,.14)', 'fg' => '#0369a1', 'label' => 'Cek Domain'],
            ];
          @endphp
          @foreach ($quickActions as $qa)
            <a href="{{ route($qa['route']) }}" class="d-flex align-items-center gap-3 px-3 py-2 rounded-3 border text-decoration-none small text-dark">
              <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;background:{{ $qa['bg'] }};color:{{ $qa['fg'] }}">
                <i class="fa-solid {{ $qa['icon'] }}" style="font-size:12px"></i>
              </span>
              {{ $qa['label'] }}
            </a>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Tiket yang butuh perhatian --}}
  @if ($openTickets->isNotEmpty())
    <div class="card border rounded-4 overflow-hidden mt-3">
      <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
        <h2 class="small fw-bold text-dark mb-0">Tiket Butuh Perhatian</h2>
        <a href="{{ route('admin.tickets') }}" class="small fw-medium text-accent text-decoration-none">Lihat semua</a>
      </div>
      <div>
        @foreach ($openTickets as $ticket)
          <a href="{{ route('admin.tickets.details', $ticket) }}" class="d-flex align-items-center justify-content-between px-4 py-3 text-decoration-none small border-bottom">
            <div>
              <p class="fw-medium text-dark mb-0">{{ $ticket->subject }}</p>
              <p class="text-muted mb-0" style="font-size:12px">{{ $ticket->ticket_number }} · {{ $ticket->client->name ?? '—' }} · {{ $ticket->last_reply_at?->diffForHumans() }}</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <span class="badge badge-soft-warning">{{ ucfirst($ticket->priority) }}</span>
              <span class="badge badge-soft-primary">{{ $ticket->status_label }}</span>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif

@endsection
