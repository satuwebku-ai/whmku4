@extends('layouts.admin')

@section('title', 'Grup Server')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Grup Server</h1>
      <p class="small text-muted mb-0">Kelompokkan server hosting. Produk yang memakai grup akan otomatis ditempatkan ke server yang masih lega, tanpa melewati batas kapasitas atau server yang sedang maintenance.</p>
    </div>
    <a href="{{ route('admin.server-groups.create') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Grup
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="py-3">Mode Pemilihan</th>
            <th class="text-center py-3">Server</th>
            <th class="text-center py-3">Akun / Kapasitas</th>
            <th class="text-center py-3">Produk</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($groups as $group)
            @php
              $used = $group->servers->sum('active_accounts_count');
              // Kapasitas "tak terbatas" kalau ada satu saja server tanpa batas.
              $unlimited = $group->servers->contains(fn ($s) => $s->max_accounts === null);
              $capacity = $unlimited ? null : $group->servers->sum('max_accounts');
            @endphp
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                {{ $group->name }}
                @if ($group->description)
                  <br><span class="text-muted fw-normal" style="font-size:11px">{{ \Illuminate\Support\Str::limit($group->description, 70) }}</span>
                @endif
              </td>
              <td class="text-muted py-3" style="font-size:12px">{{ $group->modeLabel() }}</td>
              <td class="text-center text-muted py-3">{{ $group->servers_count }}</td>
              <td class="text-center text-muted py-3">
                {{ $used }}@if ($group->servers_count) / {{ $capacity ?? '∞' }}@endif
              </td>
              <td class="text-center text-muted py-3">{{ $group->products_count }}</td>
              <td class="py-3">
                <span class="badge {{ $group->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $group->is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.server-groups.edit', $group) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.server-groups.destroy', $group) }}" data-confirm="Hapus grup ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">Belum ada grup server. Buat grup lalu centang server anggotanya.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($groups->hasPages())
      <div class="px-4 py-3 border-top">{{ $groups->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
