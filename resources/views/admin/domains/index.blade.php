@extends('layouts.admin')

@section('title', 'Domain')

@section('content')

  @include('admin.domains._nav')

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Domain Aktif</h1>
      <p class="small text-muted mb-0">Domain milik klien.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('admin.domain.search') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-magnifying-glass" style="font-size:11px"></i> Cek Domain
      </a>
      <a href="{{ route('admin.domain.add.page') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Domain
      </a>
    </div>
  </div>

  {{-- Tab status: Semua / Pending / Aktif / Expired / Cancelled --}}
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    @php
      $statusTabs = [
        ['label' => 'Semua', 'route' => 'admin.domains', 'status' => null],
        ['label' => 'Pending', 'route' => 'admin.domains.pending', 'status' => 'pending'],
        ['label' => 'Aktif', 'route' => 'admin.domains.active', 'status' => 'active'],
        ['label' => 'Expired', 'route' => 'admin.domains.expired', 'status' => 'expired'],
        ['label' => 'Cancelled', 'route' => 'admin.domains.cancelled', 'status' => 'cancelled'],
      ];
    @endphp
    @foreach ($statusTabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-1 small fw-medium text-decoration-none rounded-pill {{ $activeStatus === $tab['status'] ? 'bg-primary text-white' : 'bg-light text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari domain..." class="form-control form-control-sm" style="max-width:20rem;flex:1 1 200px">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
      @if (request('search'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Domain</th>
            <th class="py-3">Klien</th>
            <th class="py-3">Registrar</th>
            <th class="py-3">Jatuh Tempo</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $statusBadge = fn ($s) => match ($s) {
                'active' => 'badge-soft-success',
                'pending' => 'badge-soft-warning',
                'expired', 'suspended' => 'badge-soft-danger',
                default => 'badge-soft-secondary',
            };
          @endphp
          @forelse ($domains as $domain)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <a href="{{ route('admin.domains.details', $domain) }}" class="text-decoration-none text-dark">{{ $domain->domain_name }}</a>
              </td>
              <td class="text-muted py-3">{{ $domain->client->name ?? '—' }}</td>
              <td class="text-muted py-3">{{ $domain->registrar->name ?? 'Manual' }}</td>
              <td class="text-muted py-3">
                {{ $domain->expiry_date?->format('d M Y') ?? '—' }}
                @if ($domain->is_expiring_soon)
                  <span class="badge badge-soft-warning ms-1">Segera Habis</span>
                @endif
              </td>
              <td class="py-3">
                <span class="badge {{ $statusBadge($domain->status === 'expired' ? 'suspended' : $domain->status) }}">
                  {{ ucfirst($domain->status) }}
                </span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.domains.details', $domain) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Detail">
                    <i class="fa-regular fa-eye" style="font-size:12px"></i>
                  </a>
                  <a href="{{ route('admin.domain.edit.page', $domain) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.domain.delete', $domain) }}" data-confirm="Hapus data domain ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada domain di kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($domains->hasPages())
      <div class="px-4 py-3 border-top">{{ $domains->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
