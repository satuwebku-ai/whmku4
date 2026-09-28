@extends('client.layout')
@section('title', 'Domain Saya')

@section('content')
  @php
    $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'expired' => 'badge-soft-danger'];
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Domain Saya</h1>
      <p class="text-muted mb-0">Daftar domain yang Anda daftarkan.</p>
    </div>
    <form method="GET">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="active" @selected(request('status') === 'active')>Aktif</option>
        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
        <option value="expired" @selected(request('status') === 'expired')>Expired</option>
      </select>
    </form>
  </div>

  <div class="d-flex flex-column gap-3">
    @forelse ($domains as $domain)
      <a href="{{ route('client.domains.show', $domain) }}" class="dash-card dash-card-hover p-4 d-flex align-items-center justify-content-between gap-3 text-decoration-none">
        <div class="d-flex align-items-center gap-3 min-w-0">
          <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;background:rgba(6,182,212,.1);color:#0891b2">
            <i class="fa-solid fa-globe" style="font-size:15px"></i>
          </span>
          <div class="min-w-0">
            <p class="fw-semibold text-dark text-truncate mb-0">{{ $domain->domain_name }}</p>
            <p class="text-muted mt-1 mb-0" style="font-size:11px">
              @if ($domain->expiry_date)
                Berlaku sampai {{ $domain->expiry_date->format('d M Y') }}
              @else
                Tanggal kedaluwarsa belum tercatat
              @endif
            </p>
          </div>
        </div>
        <div class="text-end flex-shrink-0">
          <span class="badge {{ $badgeMap[$domain->status === 'expired' ? 'expired' : $domain->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($domain->status) }}</span>
          @if ($domain->is_expiring_soon)
            <p class="fw-medium mt-1 mb-0" style="font-size:11px;color:#b45309">Segera perpanjang</p>
          @endif
        </div>
      </a>
    @empty
      <div class="dash-card p-5 text-center">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:44px;height:44px;background:#f1f5f9;color:#94a3b8">
          <i class="fa-solid fa-globe"></i>
        </span>
        <p class="text-muted mb-3" style="font-size:14px">Anda belum punya domain.</p>
        <a href="{{ route('domain.search') }}" class="btn btn-theme"><i class="fa-solid fa-magnifying-glass" style="font-size:11px"></i> Cek Domain</a>
      </div>
    @endforelse
  </div>

  @if ($domains->hasPages())
    <div class="mt-4">{{ $domains->links('pagination.bootstrap') }}</div>
  @endif
@endsection
