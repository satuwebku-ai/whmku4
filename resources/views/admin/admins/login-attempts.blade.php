@extends('layouts.admin')

@section('title', 'Percobaan Login')

@section('content')

  @include('admin.admins._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Percobaan Login</h1>
      <p class="small text-muted mb-0">Siapa saja yang mencoba masuk, berhasil maupun gagal.</p>
    </div>
    <form method="POST" action="{{ route('admin.login-attempts.clear') }}"
          data-confirm="Hapus catatan yang lebih dari 30 hari?"
          data-confirm-title="Bersihkan Catatan" data-confirm-style="warn" data-confirm-label="Ya, Bersihkan">
      @csrf
      <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-regular fa-trash-can" style="font-size:11px"></i> Bersihkan Lama</button>
    </form>
  </div>

  @if ($suspicious->isNotEmpty())
    <div class="card border rounded-4 p-4 mb-4" style="background:#fef2f2;border-color:#fecaca!important">
      <h2 class="small fw-bold mb-1" style="color:#991b1b">
        <i class="fa-solid fa-triangle-exclamation"></i> IP dengan Kegagalan Beruntun (24 jam terakhir)
      </h2>
      <p class="mb-3" style="font-size:12px;color:#b91c1c">
        Pola ini biasanya berarti ada yang mencoba menebak password. CAPTCHA otomatis aktif untuk IP tersebut.
      </p>
      <div class="d-flex flex-wrap gap-2">
        @foreach ($suspicious as $ip)
          <span class="px-3 py-2 rounded-3 bg-white border small" style="border-color:#fecaca!important">
            <span style="font-family:monospace;color:#334155">{{ $ip->ip_address }}</span>
            <span class="fw-bold ms-1" style="color:#dc2626">{{ $ip->jumlah }}× gagal</span>
          </span>
        @endforeach
      </div>
    </div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="card border rounded-4 p-3">
        <p class="text-muted mb-1" style="font-size:12px">Total Tercatat</p>
        <p class="h4 fw-bold text-dark mb-0">{{ number_format($counts['total']) }}</p>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border rounded-4 p-3">
        <p class="text-muted mb-1" style="font-size:12px">Gagal (24 jam)</p>
        <p class="h4 fw-bold text-danger mb-0">{{ number_format($counts['failed']) }}</p>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border rounded-4 p-3">
        <p class="text-muted mb-1" style="font-size:12px">Berhasil (24 jam)</p>
        <p class="h4 fw-bold text-success mb-0">{{ number_format($counts['success']) }}</p>
      </div>
    </div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari username, email, atau IP..." class="form-control form-control-sm" style="max-width:16rem;flex:1 1 180px">
      <select name="guard" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:9rem">
        <option value="">Semua area</option>
        <option value="admin" @selected(request('guard') === 'admin')>Admin</option>
        <option value="client" @selected(request('guard') === 'client')>Klien</option>
      </select>
      <select name="result" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:9rem">
        <option value="">Semua hasil</option>
        <option value="failed" @selected(request('result') === 'failed')>Gagal</option>
        <option value="success" @selected(request('result') === 'success')>Berhasil</option>
      </select>
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Terapkan</button>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Waktu</th>
            <th class="py-3">Identitas</th>
            <th class="py-3">Area</th>
            <th class="py-3">IP</th>
            <th class="py-3">Hasil</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($attempts as $attempt)
            <tr style="{{ $attempt->successful ? '' : 'background:#fef2f2' }}">
              <td class="px-4 py-3 text-muted text-nowrap" style="font-size:12px">
                {{ $attempt->created_at->format('d M H:i:s') }}
                <span class="d-block text-muted">{{ $attempt->created_at->diffForHumans() }}</span>
              </td>
              <td class="py-3">
                <span style="font-family:monospace;font-size:12px;color:#334155">{{ $attempt->identifier }}</span>
              </td>
              <td class="py-3">
                <span class="badge badge-soft-secondary">{{ $attempt->guard === 'admin' ? 'Admin' : 'Klien' }}</span>
              </td>
              <td class="text-muted py-3" style="font-family:monospace;font-size:12px">{{ $attempt->ip_address ?: '—' }}</td>
              <td class="py-3">
                @if ($attempt->reason === 'impersonated')
                  <span class="badge badge-soft-warning" title="Bukan login klien biasa — akun diakses admin lewat fitur Login sebagai Klien">
                    <i class="fa-solid fa-user-shield"></i> {{ $attempt->reason_label }}
                  </span>
                @elseif ($attempt->successful)
                  <span class="badge badge-soft-success"><i class="fa-solid fa-check"></i> Berhasil</span>
                @else
                  <span class="badge badge-soft-danger">{{ $attempt->reason_label }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-5">Belum ada catatan percobaan login.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($attempts->hasPages())
      <div class="px-4 py-3 border-top">{{ $attempts->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
