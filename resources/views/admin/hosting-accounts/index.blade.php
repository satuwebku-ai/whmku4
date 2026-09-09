@extends('layouts.admin')

@section('title', 'Hosting Account')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Hosting Account</h1>
      <p class="small text-muted mb-0">Kelola akun hosting — suspend/unsuspend/terminate langsung lewat API server.</p>
    </div>
    <a href="{{ route('admin.hosting-account.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Hosting Account
    </a>
  </div>

  {{-- Tab status --}}
  <div class="d-flex align-items-center gap-1 mb-3 border-bottom flex-wrap">
    @php
      $tabs = [
        ['label' => 'Semua', 'route' => 'admin.hosting-accounts', 'status' => null],
        ['label' => 'Pending', 'route' => 'admin.hosting-accounts.pending', 'status' => 'pending'],
        ['label' => 'Aktif', 'route' => 'admin.hosting-accounts.active', 'status' => 'active'],
        ['label' => 'Suspended', 'route' => 'admin.hosting-accounts.suspended', 'status' => 'suspended'],
        ['label' => 'Terminated', 'route' => 'admin.hosting-accounts.terminated', 'status' => 'terminated'],
      ];
      $unlinkedCount = \App\Models\HostingAccount::where('status', 'active')->whereNull('product_id')->count();
    @endphp
    @foreach ($tabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ $activeStatus === $tab['status'] ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
    @if ($unlinkedCount > 0)
      <a href="{{ route('admin.hosting-accounts.unlinked') }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ $activeStatus === 'unlinked' ? 'border-primary text-accent' : 'border-transparent text-warning' }}">
        Belum Tertaut <span class="badge badge-soft-warning ms-1" style="font-size:10px">{{ $unlinkedCount }}</span>
      </a>
    @endif
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
            <th class="py-3">Server</th>
            <th class="text-end py-3">Harga</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $statusBadge = [
              'active' => 'badge-soft-success', 'pending' => 'badge-soft-warning',
              'suspended' => 'badge-soft-danger', 'terminated' => 'badge-soft-secondary',
              'cancelled' => 'badge-soft-secondary',
            ];
          @endphp
          @forelse ($accounts as $account)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <a href="{{ route('admin.hosting-accounts.details', $account) }}" class="text-decoration-none text-dark">{{ $account->domain }}</a>
                @if (! $account->product_id && $account->status === 'active')
                  <a href="{{ route('admin.hosting-account.edit.page', $account) }}" class="badge badge-soft-warning ms-1" style="font-size:10px"
                     title="Belum tertaut ke produk — klien tidak bisa upgrade paket sampai ini diisi">
                    <i class="fa-solid fa-link-slash"></i> Belum Tertaut
                  </a>
                @endif
              </td>
              <td class="text-muted py-3">{{ $account->client->name ?? '—' }}</td>
              <td class="text-muted py-3">{{ $account->serverModel->name ?? 'Manual' }}</td>
              <td class="text-end text-dark py-3">Rp {{ number_format($account->price, 0, ',', '.') }}</td>
              <td class="py-3">
                <span class="badge {{ $statusBadge[$account->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($account->status) }}</span>
                @if ($account->cancellation_status === 'requested')
                  <span class="badge badge-soft-danger d-block mt-1" style="width:fit-content"><i class="fa-solid fa-triangle-exclamation"></i> Pembatalan</span>
                @endif
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.hosting-accounts.details', $account) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Detail">
                    <i class="fa-regular fa-eye" style="font-size:12px"></i>
                  </a>
                  <a href="{{ route('admin.hosting-account.edit.page', $account) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.hosting-account.delete', $account) }}" data-confirm="Hapus data hosting account ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada hosting account di kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($accounts->hasPages())
      <div class="px-4 py-3 border-top">{{ $accounts->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
