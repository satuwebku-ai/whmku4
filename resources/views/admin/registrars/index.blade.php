@extends('layouts.admin')

@section('title', 'Registrar Domain')

@section('content')

  @include('admin.domains._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Registrar Domain</h1>
      <p class="small text-muted mb-0">Kelola koneksi ke Namecheap, Liqu.id, atau ResellBiz untuk registrasi domain otomatis.</p>
    </div>
    <a href="{{ route('admin.registrars.create') }}" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Registrar
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="py-3">Provider</th>
            <th class="py-3">Mode / Endpoint</th>
            <th class="text-center py-3">TLD</th>
            <th class="text-center py-3">Domain</th>
            <th class="py-3">Saldo</th>
            <th class="py-3">Cek Terakhir</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($registrars as $registrar)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                {{ $registrar->name }}
                @if ($registrar->is_default)
                  <span class="badge badge-soft-success ms-1">Default</span>
                @endif
              </td>
              <td class="text-muted py-3">
                {{ ['namecheap' => 'Namecheap', 'liquid' => 'Liqu.id', 'resellbiz' => 'ResellBiz', 'dnama' => 'DNAMA'][$registrar->provider] ?? ucfirst($registrar->provider) }}
              </td>
              <td class="text-muted py-3" style="font-size:12px">
                @if ($registrar->provider === 'namecheap')
                  {{ $registrar->sandbox ? 'Sandbox' : 'Production' }}
                @else
                  {{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', (string) $registrar->api_url), 28) ?: '—' }}
                @endif
              </td>
              <td class="text-center text-muted py-3">{{ $registrar->tlds_count }}</td>
              <td class="text-center text-muted py-3">{{ $registrar->domains_count }}</td>
              <td class="py-3" style="font-size:12px">
                @if (isset($balances[$registrar->id]))
                  @if ($balances[$registrar->id])
                    @php
                      $bal = $balances[$registrar->id];
                      $mataUang = $bal['currency'] ?? 'USD';
                      // Rupiah konvensinya tanpa desimal & pemisah ribuan
                      // titik -- mata uang lain (USD dkk) pakai 2 desimal
                      // seperti sebelumnya. Liqu.id TIDAK PERNAH mengirim
                      // info mata uang di endpoint saldo (dikonfirmasi
                      // dari dashboard mereka: akun ini pakai USD), jadi
                      // default 'USD' di atas khusus menjaga baris Liqu.id
                      // tetap tampil seperti sebelumnya.
                    @endphp
                    @if ($mataUang === 'IDR')
                      <span class="fw-semibold {{ $bal['balance'] < 50000 ? 'text-danger' : 'text-dark' }}">
                        Rp {{ number_format($bal['balance'], 0, ',', '.') }}
                      </span>
                      @if ($bal['balance'] < 50000)
                        <br><span class="text-danger">Menipis</span>
                      @endif
                    @else
                      <span class="fw-semibold {{ $bal['balance'] < 5 ? 'text-danger' : 'text-dark' }}">
                        ${{ number_format($bal['balance'], 2) }}
                      </span>
                      @if ($bal['balance'] < 5)
                        <br><span class="text-danger">Menipis</span>
                      @endif
                    @endif
                  @else
                    <span class="text-muted">Gagal diambil</span>
                  @endif
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-muted py-3" style="font-size:12px">
                @if ($registrar->last_checked_at)
                  {{ $registrar->last_checked_at->diffForHumans() }}
                  <br>
                  <span class="{{ $registrar->last_check_status === 'ok' ? 'text-success' : 'text-danger' }}">
                    {{ $registrar->last_check_status === 'ok' ? 'Terhubung' : \Illuminate\Support\Str::limit($registrar->last_check_status, 40) }}
                  </span>
                @else
                  Belum pernah dicek
                @endif
              </td>
              <td class="py-3">
                <span class="badge {{ $registrar->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $registrar->is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <form method="POST" action="{{ route('admin.registrars.test-connection', $registrar) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Tes Koneksi">
                      <i class="fa-solid fa-plug" style="font-size:11px"></i>
                    </button>
                  </form>
                  @if ($supportsSync[$registrar->id] ?? false)
                    <form method="POST" action="{{ route('admin.registrars.sync-tlds', $registrar) }}"
                          data-confirm="Impor daftar TLD dari registrar ini? Harga TLD yang sudah ada tidak akan diubah." data-confirm-title="Sinkronkan TLD" data-confirm-style="info" data-confirm-label="Ya, Impor">
                      @csrf
                      <button type="submit" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Sinkronkan daftar TLD">
                        <i class="fa-solid fa-rotate" style="font-size:11px"></i>
                      </button>
                    </form>
                    <a href="{{ route('admin.registrars.transactions', $registrar) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Riwayat Transaksi">
                      <i class="fa-solid fa-receipt" style="font-size:11px"></i>
                    </a>
                    <a href="{{ route('admin.registrars.diagnostics', $registrar) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Diagnosa (mata uang, saldo, format harga)">
                      <i class="fa-solid fa-stethoscope" style="font-size:11px"></i>
                    </a>
                  @endif
                  <a href="{{ route('admin.registrars.edit', $registrar) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:11px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.registrars.destroy', $registrar) }}" data-confirm="Hapus registrar ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:11px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-muted py-5">Belum ada registrar terhubung.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($registrars->hasPages())
      <div class="px-4 py-3 border-top">{{ $registrars->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection