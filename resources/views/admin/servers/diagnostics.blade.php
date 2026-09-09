@extends('layouts.admin')

@section('title', 'Diagnosa Server — ' . $server->name)

@section('content')

  <a href="{{ route('admin.servers.index') }}" class="text-decoration-none text-muted" style="font-size:12px">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Server
  </a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-2">Diagnosa — {{ $server->name }}</h1>
  <p class="small text-muted mb-4">
    Data langsung dari {{ ucfirst($server->panel) }} — cuma membaca, tidak mengubah apa pun di server.
  </p>

  @if ($apiError)
    <div class="card border rounded-4 p-3 mb-4 small" style="background:#fef2f2;border-color:#fecaca!important;color:#991b1b">
      <i class="fa-solid fa-circle-exclamation"></i> {{ $apiError }}
    </div>
  @endif

  {{-- Bandingkan catatan Hosting Account kita vs kondisi sungguhan di server --}}
  <div class="card border rounded-4 overflow-hidden mb-4">
    <div class="px-4 py-3 border-bottom">
      <h2 class="small fw-bold text-dark mb-0">Hosting Account vs Kondisi Sungguhan di WHM</h2>
      <p class="text-muted mb-0 mt-1" style="font-size:12px">Domain di kolom kanan harus ADA di server — kalau tidak, akunnya belum pernah benar-benar dibuat (biasanya karena provisioning gagal).</p>
    </div>

    @if ($accountsError)
      <div class="px-4 py-3 small text-danger" style="background:#fef2f2">
        <i class="fa-solid fa-circle-exclamation"></i> {{ $accountsError }}
      </div>
    @endif

    <div>
      @forelse ($ourAccounts as $acc)
        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
          <div>
            <p class="small text-dark mb-0">{{ $acc['domain'] }} <span class="text-muted" style="font-size:11px">(ID {{ $acc['id'] }})</span></p>
            <p class="text-muted mb-0" style="font-size:11px">Status: {{ $acc['status'] }} · Provisioning: {{ $acc['provision_status'] }}</p>
          </div>
          @if ($acc['ada_di_whm'])
            <span class="badge badge-soft-success"><i class="fa-solid fa-check" style="font-size:10px"></i> Ada di server</span>
          @else
            <span class="badge badge-soft-danger"><i class="fa-solid fa-xmark" style="font-size:10px"></i> TIDAK ada di server</span>
          @endif
        </div>
      @empty
        <p class="text-center text-muted small py-4 mb-0">Belum ada Hosting Account yang tercatat untuk server ini.</p>
      @endforelse
    </div>

    @if (! empty($orphanWhmDomains))
      <div class="px-4 py-3 border-top" style="background:#fffbeb">
        <p class="fw-medium mb-1" style="font-size:12px;color:#92400e">
          <i class="fa-solid fa-triangle-exclamation"></i> Ada di server tapi TIDAK tercatat di sistem kita ({{ count($orphanWhmDomains) }}):
        </p>
        <p class="mb-1" style="font-size:12px;color:#b45309">{{ implode(', ', $orphanWhmDomains) }}</p>
        <p class="mb-0" style="font-size:11px;color:#d97706">Biasanya ini dibuat manual langsung di WHM, di luar Lumora — atau sisa dari reseller lain di server yang sama.</p>
      </div>
    @endif
  </div>

  {{-- Cocokkan produk vs paket sungguhan — ini yang paling penting --}}
  <div class="card border rounded-4 overflow-hidden mb-4">
    <div class="px-4 py-3 border-bottom">
      <h2 class="small fw-bold text-dark mb-0">Produk yang Terhubung ke Server Ini</h2>
      <p class="text-muted mb-0 mt-1" style="font-size:12px">Nama paket di kolom kanan harus PERSIS sama dengan yang ada di WHM (besar-kecil huruf ikut berpengaruh).</p>
    </div>
    <div>
      @forelse ($products as $p)
        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
          <p class="small text-dark mb-0">{{ $p['name'] }}</p>
          <div class="d-flex align-items-center gap-2">
            <code class="px-2 py-1 rounded" style="font-size:11px;background:#f1f5f9">{{ $p['panel_package'] ?: '(kosong — mode manual)' }}</code>
            @if (is_null($p['matches']))
              <span class="badge badge-soft-secondary">Manual</span>
            @elseif ($p['matches'])
              <span class="badge badge-soft-success"><i class="fa-solid fa-check" style="font-size:10px"></i> Cocok</span>
            @else
              <span class="badge badge-soft-danger"><i class="fa-solid fa-xmark" style="font-size:10px"></i> Tidak ditemukan di server</span>
            @endif
          </div>
        </div>
      @empty
        <p class="text-center text-muted small py-4 mb-0">Belum ada produk yang terhubung ke server ini.</p>
      @endforelse
    </div>
  </div>

  {{-- Daftar paket sungguhan di server, untuk referensi --}}
  <div class="card border rounded-4 p-4">
    <h2 class="small fw-bold text-dark mb-2">Semua Paket di Server Ini ({{ count($packages) }})</h2>
    @if (count($packages))
      <div class="d-flex flex-wrap gap-2">
        @foreach ($packages as $pkg)
          <code class="px-2 py-1 rounded" style="font-size:11px;background:#f1f5f9">{{ $pkg }}</code>
        @endforeach
      </div>
    @else
      <p class="text-muted small mb-0">Tidak bisa diambil — lihat pesan galat di atas.</p>
    @endif
  </div>

@endsection
