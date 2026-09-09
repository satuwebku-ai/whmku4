@extends('layouts.admin')

@section('title', 'Manajemen Admin')

@section('content')

  @include('admin.admins._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Manajemen Admin</h1>
      <p class="small text-muted mb-0">Kelola akun staf beserta tingkat aksesnya.</p>
    </div>
    <a href="{{ route('admin.admin.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Admin
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, username, email..." class="form-control form-control-sm" style="max-width:16rem">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="py-3">Username</th>
            <th class="py-3">Peran</th>
            <th class="py-3">Login Terakhir</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $roleBadge = fn ($row) => $row->isSuperadmin() ? 'badge-soft-success' : ($row->isStaff() ? 'badge-soft-secondary' : 'badge-soft-warning');
          @endphp
          @forelse ($admins as $row)
            <tr>
              <td class="px-4 py-3">
                <p class="fw-medium text-dark mb-0">
                  {{ $row->name }}
                  @if ($row->id === auth('admin')->id())
                    <span class="text-accent fw-normal" style="font-size:10px">(Anda)</span>
                  @endif
                </p>
                <p class="text-muted mb-0" style="font-size:12px">{{ $row->email }}</p>
              </td>
              <td class="text-muted py-3" style="font-family:monospace">{{ $row->username }}</td>
              <td class="py-3">
                <span class="badge {{ $roleBadge($row) }}">{{ $row->role_label }}</span>
              </td>
              <td class="text-muted py-3" style="font-size:12px">
                {{ $row->last_login_at?->diffForHumans() ?? 'Belum pernah' }}
                @if ($row->last_login_ip)
                  <span class="d-block text-muted">{{ $row->last_login_ip }}</span>
                @endif
              </td>
              <td class="py-3">
                <span class="badge {{ $row->is_active ? 'badge-soft-success' : 'badge-soft-danger' }}">
                  {{ $row->is_active ? 'Aktif' : 'Diblokir' }}
                </span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  @if ($row->id !== auth('admin')->id())
                    <form method="POST" action="{{ route('admin.admin.status') }}"
                          data-confirm="{{ $row->is_active ? 'Blokir' : 'Aktifkan' }} akun {{ $row->username }}?"
                          data-confirm-title="{{ $row->is_active ? 'Blokir Admin' : 'Aktifkan Admin' }}"
                          data-confirm-style="{{ $row->is_active ? 'danger' : 'info' }}"
                          data-confirm-label="Ya, Lanjutkan">
                      @csrf
                      <input type="hidden" name="admin_id" value="{{ $row->id }}">
                      <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0"
                              title="{{ $row->is_active ? 'Blokir' : 'Aktifkan' }}">
                        <i class="fa-solid {{ $row->is_active ? 'fa-ban' : 'fa-circle-check' }}" style="font-size:12px"></i>
                      </button>
                    </form>
                  @endif

                  <a href="{{ route('admin.admin.edit.page', $row) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>

                  @if ($row->id !== auth('admin')->id())
                    <form method="POST" action="{{ route('admin.admin.delete', $row) }}"
                          data-confirm="Hapus admin {{ $row->username }}? Tindakan ini tidak bisa dibatalkan."
                          data-confirm-title="Hapus Admin" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0">
                        <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada admin lain.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($admins->hasPages())
      <div class="px-4 py-3 border-top">{{ $admins->links('pagination.bootstrap') }}</div>
    @endif
  </div>

  <div class="card border rounded-4 p-4 mt-3">
    <h2 class="small fw-bold text-dark mb-3">Arti Peran</h2>
    @foreach (\App\Models\Admin::ROLES as $key => $desc)
      <div class="d-flex gap-2 mb-2 small">
        <span class="badge {{ $key === 'superadmin' ? 'badge-soft-success' : ($key === 'staff' ? 'badge-soft-secondary' : 'badge-soft-warning') }} flex-shrink-0">{{ ucfirst($key) }}</span>
        <span class="text-muted">{{ \Illuminate\Support\Str::after($desc, '— ') }}</span>
      </div>
    @endforeach
  </div>

@endsection
