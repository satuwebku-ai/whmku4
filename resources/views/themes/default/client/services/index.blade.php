@extends('client.layout')
@section('title', 'Layanan Saya')

@section('content')
  @php
    $badgeMap = [
      'active' => 'badge-soft-success', 'pending' => 'badge-soft-warning',
      'suspended' => 'badge-soft-danger', 'terminated' => 'badge-soft-secondary',
    ];
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Layanan Saya</h1>
      <p class="text-muted mb-0">Daftar akun hosting Anda.</p>
    </div>
    <form method="GET">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="active" @selected(request('status') === 'active')>Aktif</option>
        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
        <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
        <option value="terminated" @selected(request('status') === 'terminated')>Terminated</option>
      </select>
    </form>
  </div>

  <div class="d-flex flex-column gap-3">
    @forelse ($services as $service)
      <a href="{{ route('client.services.show', $service) }}" class="dash-card dash-card-hover p-4 d-flex align-items-center justify-content-between gap-3 text-decoration-none">
        <div class="d-flex align-items-center gap-3 min-w-0">
          <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;background:rgba(79,70,229,.1);color:var(--lumora-theme)">
            <i class="fa-solid fa-server" style="font-size:15px"></i>
          </span>
          <div class="min-w-0">
            <p class="fw-semibold text-dark text-truncate mb-0">{{ $service->domain }}</p>
            <p class="text-muted mb-0" style="font-size:14px">{{ $service->package }}</p>
            @if ($service->next_due_date)
              <p class="text-muted mt-1 mb-0" style="font-size:11px">Jatuh tempo berikutnya: {{ $service->next_due_date->format('d M Y') }}</p>
            @endif
          </div>
        </div>
        <div class="text-end flex-shrink-0">
          <span class="badge {{ $badgeMap[$service->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($service->status) }}</span>
          <p class="fw-semibold text-dark mt-2 mb-0" style="font-size:14px">Rp {{ number_format($service->price, 0, ',', '.') }}</p>
          <p class="text-muted mb-0" style="font-size:11px">{{ str_replace('_', ' ', $service->billing_cycle) }}</p>
        </div>
      </a>
    @empty
      <div class="dash-card p-5 text-center">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:44px;height:44px;background:#f1f5f9;color:#94a3b8">
          <i class="fa-solid fa-server"></i>
        </span>
        <p class="text-muted mb-3" style="font-size:14px">Anda belum punya layanan hosting.</p>
        <a href="{{ route('catalog.index') }}" class="btn btn-theme"><i class="fa-solid fa-cart-plus" style="font-size:11px"></i> Pesan Layanan</a>
      </div>
    @endforelse
  </div>

  @if ($services->hasPages())
    <div class="mt-4">{{ $services->links('pagination.bootstrap') }}</div>
  @endif
@endsection
