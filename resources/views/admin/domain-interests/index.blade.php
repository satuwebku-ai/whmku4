@extends('layouts.admin')

@section('title', 'Minat Domain')

@section('content')
  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Minat Domain</h1>
    <p class="small text-muted mb-0">
      Pencarian, domain yang masuk keranjang, dan checkout dari client yang sudah login.
      Data pengunjung anonim tidak disimpan.
    </p>
  </div>

  @php
    $labels = [
      'search' => 'Pencarian',
      'cart' => 'Keranjang',
      'checkout' => 'Checkout',
    ];
    $badges = [
      'search' => 'badge-soft-secondary',
      'cart' => 'badge-soft-warning',
      'checkout' => 'badge-soft-success',
    ];
  @endphp

  <div class="card border rounded-4 overflow-hidden">
    <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex gap-2">
        <a href="{{ route('admin.domain-interests.index') }}"
           class="btn btn-sm {{ ! $type ? 'btn-primary' : 'btn-outline-secondary' }}">Semua</a>
        @foreach ($labels as $value => $label)
          <a href="{{ route('admin.domain-interests.index', ['type' => $value]) }}"
             class="btn btn-sm {{ $type === $value ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
      </div>
      <span class="small text-muted">{{ $interests->total() }} catatan</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Domain</th>
            <th class="py-3">Client</th>
            <th class="py-3">Aktivitas</th>
            <th class="py-3">Sumber</th>
            <th class="text-end px-4 py-3">Waktu</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($interests as $interest)
            <tr>
              <td class="px-4 py-3">
                <div class="fw-semibold text-dark">{{ $interest->domain_name }}</div>
                <div class="small text-muted">
                  @if ($interest->years) {{ $interest->years }} tahun · @endif
                  {{ $interest->tld?->extension ?? $interest->tldPremium?->extension ?? '—' }}
                </div>
              </td>
              <td class="py-3">
                <div class="small fw-medium text-dark">{{ $interest->client?->name ?? 'Client dihapus' }}</div>
                <div class="small text-muted">{{ $interest->client?->email ?? '—' }}</div>
              </td>
              <td class="py-3">
                <span class="badge {{ $badges[$interest->event_type] ?? 'badge-soft-secondary' }}">
                  {{ $labels[$interest->event_type] ?? ucfirst($interest->event_type) }}
                </span>
              </td>
              <td class="py-3 small text-muted">{{ $interest->source ?: '—' }}</td>
              <td class="text-end px-4 py-3 small text-muted">
                {{ $interest->created_at?->diffForHumans() }}
                <span class="d-block" style="font-size:10px">{{ $interest->created_at?->format('d M Y H:i') }}</span>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-5">Belum ada minat domain tercatat.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($interests->hasPages())
      <div class="px-4 py-3 border-top">{{ $interests->links() }}</div>
    @endif
  </div>
@endsection