@extends('layouts.admin')

@section('title', 'Diagnosa — ' . $server->name)

@php
  /** Kotak error kecil yang dipakai berulang di tiap bagian. */
  $err = function ($message) {
    return '<p class="mb-0" style="font-size:12px;color:#b91c1c"><i class="fa-solid fa-circle-exclamation"></i> ' . e($message) . '</p>';
  };
@endphp

@section('content')

  <a href="{{ route('admin.servers.index') }}" class="text-decoration-none text-muted" style="font-size:12px">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Server
  </a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-2">Diagnosa — {{ $server->name }}</h1>
  <p class="small text-muted mb-4">
    <i class="fa-solid fa-cloud"></i> IDCloudHost — semua data diambil langsung dari API, hanya membaca, tidak mengubah apa pun.
  </p>

  <div class="row g-3">

    {{-- ══════ 1. AKUN ══════ --}}
    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-user text-muted"></i> Akun</h2>
        @if ($sections['user']['error'])
          {!! $err($sections['user']['error']) !!}
        @else
          @php $u = $sections['user']['data']; $p = $u['profile_data'] ?? []; @endphp
          <div class="row g-3">
            <div class="col-6">
              <p class="text-muted mb-0" style="font-size:11px">Email / Nama Akun</p>
              <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $u['name'] ?? '—' }}</p>
            </div>
            <div class="col-6">
              <p class="text-muted mb-0" style="font-size:11px">User ID</p>
              <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $u['id'] ?? '—' }}</p>
            </div>
            @if (!empty($p['first_name']) || !empty($p['last_name']))
              <div class="col-6">
                <p class="text-muted mb-0" style="font-size:11px">Nama Lengkap</p>
                <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) }}</p>
              </div>
            @endif
            @if (!empty($p['phone_number']))
              <div class="col-6">
                <p class="text-muted mb-0" style="font-size:11px">Telepon</p>
                <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $p['phone_number'] }}</p>
              </div>
            @endif
            <div class="col-6">
              <p class="text-muted mb-0" style="font-size:11px">Aktivitas Terakhir</p>
              <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $u['last_activity'] ?? '—' }}</p>
            </div>
          </div>
        @endif
      </div>
    </div>

    {{-- ══════ 2. NILAI DEPOSIT ══════ --}}
    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-wallet text-muted"></i> Nilai Deposit / Kredit</h2>
        @if ($sections['billing']['error'])
          {!! $err($sections['billing']['error']) !!}
        @elseif (! $billingAccount)
          <p class="mb-2" style="font-size:12px;color:#92400e">
            <i class="fa-solid fa-circle-exclamation"></i> Tidak ada billing account yang cocok.
            @if (filled($server->api_username))
              Kolom <b>Billing Account ID</b> di server ini diisi <code>{{ $server->api_username }}</code> —
              pastikan angka itu benar-benar ada di daftar di bawah.
            @else
              Kolom Billing Account ID dikosongkan, jadi sistem mencari yang berstatus default.
            @endif
          </p>
          <p class="text-muted mb-1" style="font-size:11px">Respons mentah dari API (untuk diagnosa):</p>
          <pre class="rounded-3 p-3 mb-0" style="background:#1e293b;color:#f1f5f9;font-size:11px;overflow-x:auto;max-height:12rem">{{ json_encode($sections['billing']['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Kalau isinya <code>[]</code> (kosong) padahal di dashboard IDCloudHost ada saldo, kemungkinan
            API Token-mu bertipe <b>restricted</b> — token seperti itu sering tidak diizinkan mengakses
            endpoint <code>/payment/</code>. Coba buat token baru yang tidak dibatasi.
          </p>
        @else
          @php
            $totals = $billingAccount['running_totals'] ?? [];
            $credit = $billingAccount['credit_amount'] ?? null;
            $available = $totals['credit_available'] ?? $credit;
            $unpaid = $billingAccount['unpaid_amount'] ?? 0;
          @endphp
          <div class="rounded-3 p-3 mb-3" style="background:linear-gradient(135deg,#4f46e5,#4338ca)">
            <p class="mb-0" style="font-size:11px;color:rgba(255,255,255,.7)">Kredit Tersedia</p>
            <p class="fw-bold text-white mb-0" style="font-size:1.6rem">{{ number_format((float) $available, 2) }}</p>
            <p class="mb-0" style="font-size:10px;color:rgba(255,255,255,.6)">
              Akun: {{ $billingAccount['title'] ?? '—' }} (ID {{ $billingAccount['id'] ?? '?' }})
            </p>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <div class="rounded-3 border p-2">
                <p class="text-muted mb-0" style="font-size:10px">Tunggakan</p>
                <p class="fw-semibold mb-0 {{ (float) $unpaid > 0 ? 'text-danger' : 'text-dark' }}" style="font-size:14px">{{ number_format((float) $unpaid, 2) }}</p>
              </div>
            </div>
            <div class="col-6">
              <div class="rounded-3 border p-2">
                <p class="text-muted mb-0" style="font-size:10px">Berjalan Bulan Ini</p>
                <p class="fw-semibold text-dark mb-0" style="font-size:14px">{{ number_format((float) ($totals['ongoing'] ?? 0), 2) }}</p>
              </div>
            </div>
          </div>
          @if (($billingAccount['restriction_level'] ?? '') && $billingAccount['restriction_level'] !== 'NONE')
            <p class="mt-2 mb-0 rounded-3 px-3 py-2" style="font-size:11px;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Status akun: <b>{{ $billingAccount['restriction_level'] }}</b>
              @if (! empty($billingAccount['suspend_reason'])) — {{ $billingAccount['suspend_reason'] }} @endif
            </p>
          @endif
          <p class="text-muted mt-2 mb-0" style="font-size:10px">
            Mata uang mengikuti pengaturan akun IDCloudHost — cek di dashboard mereka kalau ragu.
          </p>
        @endif
      </div>
    </div>

    {{-- ══════ 3. NILAI HARGA JUAL ══════ --}}
    <div class="col-12">
      <div class="card border rounded-4 p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
          <h2 class="small fw-bold text-dark mb-0"><i class="fa-solid fa-tags text-muted"></i> Nilai Harga Jual (kartu harga server ini)</h2>
          <a href="{{ route('admin.servers.edit', $server) }}" class="btn btn-outline-secondary btn-sm">Ubah Kartu Harga</a>
        </div>

        @php $rateEmpty = collect($rateCard)->pluck('jual')->filter()->isEmpty(); @endphp
        @if ($rateEmpty)
          <p class="mb-2 rounded-3 px-3 py-2" style="font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a">
            <i class="fa-solid fa-triangle-exclamation"></i>
            @if ($server->pricing_mode === 'markup')
              Server ini pakai mode <b>Markup</b>, tapi harga modal belum pernah ditarik atau markup masih 0 —
              buka <a href="{{ route('admin.servers.edit', $server) }}" class="text-decoration-underline fw-medium" style="color:inherit">Edit Server</a>,
              tekan "Tarik Harga Modal Sekarang", lalu isi persentase markup.
            @else
              Kartu harga jual belum diisi — tagihan per jam TIDAK bisa dihitung otomatis untuk VM di server ini.
            @endif
          </p>
        @elseif ($server->pricing_mode === 'markup')
          <p class="mb-2 rounded-3 px-3 py-2" style="font-size:12px;color:#166534;background:#f0fdf4;border:1px solid #bbf7d0">
            <i class="fa-solid fa-circle-check"></i>
            Harga jual dihitung otomatis dari harga modal + markup <b>{{ number_format((float) $server->markup_percent, 2) }}%</b>.
          </p>
        @endif

        @if ($sections['pricing']['error'])
          <p class="mb-2" style="font-size:11px;color:#b45309">
            <i class="fa-solid fa-circle-info"></i> Harga modal tidak bisa diambil: {{ $sections['pricing']['error'] }}
          </p>
        @endif

        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0" style="font-size:13px">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="py-2">Komponen</th>
                <th class="text-end py-2">Modal (IDCloudHost)</th>
                <th class="text-end py-2">Harga Jual (kamu)</th>
                <th class="text-end py-2">Margin</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rateCard as $label => $row)
                @php
                  $modal = $row['modal'];
                  $jual = $row['jual'] !== null ? (float) $row['jual'] : null;
                  $margin = ($modal !== null && $jual !== null) ? $jual - (float) $modal : null;
                  $marginPct = ($modal !== null && (float) $modal > 0 && $jual !== null)
                    ? ($jual - (float) $modal) / (float) $modal * 100 : null;
                @endphp
                <tr>
                  <td class="py-2 fw-medium text-dark">{{ $label }}</td>
                  <td class="text-end py-2 text-muted">
                    {{ $modal !== null ? number_format((float) $modal, 4) : '—' }}
                    @if (! empty($row['tier']))
                      <span class="d-block" style="font-size:10px;color:#b45309">naik → {{ $row['tier'] }}</span>
                    @endif
                  </td>
                  <td class="text-end py-2 text-dark">{{ $jual !== null ? number_format($jual, 6) : '—' }}</td>
                  <td class="text-end py-2">
                    @if ($margin !== null)
                      <span class="{{ $margin >= 0 ? 'text-success' : 'text-danger fw-semibold' }}">
                        {{ $margin >= 0 ? '+' : '' }}{{ number_format($margin, 6) }}
                        @if ($marginPct !== null)
                          <span class="d-block" style="font-size:10px">{{ number_format($marginPct, 1) }}%</span>
                        @endif
                      </span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @php $modalKosong = collect($rateCard)->pluck('modal')->filter(fn ($v) => (float) $v > 0)->isEmpty(); @endphp
        @if ($modalKosong && ! $sections['pricing']['error'])
          <p class="text-muted mb-1" style="font-size:11px">
            <i class="fa-solid fa-circle-exclamation"></i>
            Semua harga modal terbaca 0 — ini respons mentah <code>/pricing/policy</code> untuk diagnosa:
          </p>
          <pre class="rounded-3 p-3 mb-2" style="background:#1e293b;color:#f1f5f9;font-size:11px;overflow-x:auto;max-height:12rem">{{ json_encode($sections['pricing']['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif
        <p class="text-muted mb-0" style="font-size:11px">
          <i class="fa-solid fa-circle-info"></i>
          Harga modal diambil langsung dari <code>/v1/pricing/policy</code> IDCloudHost (per jam).
          Modal CPU &amp; RAM sudah dinormalkan ke satuan yang sama dengan kartu hargamu (per 1 vCPU / per 1 GB).
          <b class="text-danger">Margin negatif berarti kamu rugi</b> untuk komponen itu.
        </p>

        <p class="fw-bold text-muted mb-2 mt-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">Produk VPS di server ini</p>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" style="font-size:13px">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="py-2">Produk</th>
                <th class="py-2">Spesifikasi</th>
                <th class="text-end py-2">Harga Jual / Jam</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($products as $product)
                @php
                  $spec = $product['spec'];
                  $hourly = ($spec && !$rateEmpty)
                    ? \App\Services\Billing\HourlyRateCalculator::calculate($server, $spec + ['backup_enabled' => $spec['backup_enabled'] ?? false, 'snapshot_gb' => $spec['snapshot_gb'] ?? 0])
                    : null;
                @endphp
                <tr>
                  <td class="py-2 fw-medium text-dark">{{ $product['name'] }}</td>
                  <td class="py-2 text-muted">
                    @if ($spec)
                      {{ $spec['vcpu'] ?? '?' }} vCPU · {{ $spec['ram'] ?? '?' }} MB · {{ $spec['disk'] ?? '?' }} GB ·
                      {{ $spec['os_name'] ?? '' }} {{ $spec['os_version'] ?? '' }}
                    @else
                      <span style="color:#b45309"><i class="fa-solid fa-triangle-exclamation"></i> Spesifikasi JSON belum diisi</span>
                    @endif
                  </td>
                  <td class="text-end py-2 fw-semibold text-dark">
                    {{ $hourly !== null ? 'Rp ' . number_format($hourly, 2, ',', '.') : '—' }}
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted py-4">Belum ada produk yang menunjuk ke server ini.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ══════ 4. JENIS OS ══════ --}}
    <div class="col-12 col-lg-7">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-brands fa-linux text-muted"></i> Jenis OS Tersedia</h2>
        @if ($sections['images']['error'])
          {!! $err($sections['images']['error']) !!}
        @else
          <div class="table-responsive" style="max-height:20rem;overflow-y:auto">
            <table class="table table-sm align-middle mb-0" style="font-size:13px">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc;position:sticky;top:0">
                  <th class="py-2">OS</th>
                  <th class="py-2">os_name</th>
                  <th class="py-2">Versi (os_version)</th>
                  <th class="py-2">Tipe</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($sections['images']['data'] as $img)
                  <tr>
                    <td class="py-2 fw-medium text-dark">{{ $img['display_name'] ?? '—' }}</td>
                    <td class="py-2 text-muted" style="font-family:monospace;font-size:11px">{{ $img['os_name'] ?? '' }}</td>
                    <td class="py-2 text-muted" style="font-size:11px">
                      @foreach ($img['versions'] ?? [] as $v)
                        <span class="d-inline-block me-1 mb-1 px-2 py-1 rounded-2" style="background:#f1f5f9;font-family:monospace">{{ $v['os_version'] ?? '' }}</span>
                      @endforeach
                    </td>
                    <td class="py-2">
                      <span class="badge {{ !empty($img['is_app_catalog']) ? 'badge-soft-warning' : 'badge-soft-secondary' }}">
                        {{ !empty($img['is_app_catalog']) ? 'App Catalog' : 'Plain OS' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada image.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Salin persis <code>os_name</code> &amp; <code>os_version</code> ke JSON spesifikasi produk (kolom Panel Package).
          </p>
        @endif
      </div>
    </div>

    {{-- ══════ 5. BATASAN PARAMETER VM ══════ --}}
    <div class="col-12 col-lg-5">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-1"><i class="fa-solid fa-sliders text-muted"></i> Batasan Spesifikasi VM</h2>
        <p class="text-muted mb-3" style="font-size:11px">
          Aturan dari IDCloudHost — paket VPS yang kamu jual HARUS di dalam rentang ini, kalau tidak provisioning akan ditolak.
        </p>
        @if ($sections['params']['error'])
          {!! $err($sections['params']['error']) !!}
        @else
          <div class="d-flex flex-column gap-2">
            @foreach ($sections['params']['data'] as $param)
              @if (in_array($param['parameter'] ?? '', ['vcpu', 'ram', 'disks']))
                <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2">
                  <div>
                    <p class="fw-medium text-dark mb-0" style="font-size:13px;text-transform:uppercase">{{ $param['parameter'] }}</p>
                    <p class="text-muted mb-0" style="font-size:11px">{{ $param['description'] ?? '' }}</p>
                  </div>
                  <span class="badge badge-soft-secondary text-nowrap">
                    {{ $param['min'] ?? '?' }} – {{ $param['max'] ?? '?' }}
                  </span>
                </div>
              @endif
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- ══════ 6. RESOURCE POOL & LOKASI ══════ --}}
    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-server text-muted"></i> Kelas Server (Resource Pool)</h2>
        @if ($sections['pools']['error'])
          {!! $err($sections['pools']['error']) !!}
        @else
          @forelse ($sections['pools']['data'] as $pool)
            <div class="rounded-3 border px-3 py-2 mb-2">
              <div class="d-flex align-items-center gap-2">
                <p class="fw-medium text-dark mb-0" style="font-size:13px">{{ $pool['name'] ?? '—' }}</p>
                @if (!empty($pool['is_default_designated']))
                  <span class="badge badge-soft-success">Default</span>
                @endif
              </div>
              <p class="text-muted mb-0" style="font-size:11px">{{ $pool['description'] ?? '' }}</p>
              <p class="text-muted mb-0" style="font-size:10px;font-family:monospace">{{ $pool['uuid'] ?? '' }}</p>
            </div>
          @empty
            <p class="text-muted mb-0" style="font-size:13px">Tidak ada resource pool.</p>
          @endforelse
        @endif
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-location-dot text-muted"></i> Lokasi Datacenter</h2>
        @if ($sections['locations']['error'])
          {!! $err($sections['locations']['error']) !!}
        @else
          @forelse ($sections['locations']['data'] as $loc)
            <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2 mb-2">
              <div>
                <p class="fw-medium text-dark mb-0" style="font-size:13px">{{ $loc['display_name'] ?? '—' }}</p>
                <p class="text-muted mb-0" style="font-size:11px">{{ $loc['description'] ?? '' }}</p>
              </div>
              <div class="text-end">
                <span class="badge badge-soft-secondary" style="font-family:monospace">{{ $loc['slug'] ?? '' }}</span>
                @if (!empty($loc['is_default']))
                  <span class="d-block text-success mt-1" style="font-size:10px">default</span>
                @endif
              </div>
            </div>
          @empty
            <p class="text-muted mb-0" style="font-size:13px">Tidak ada lokasi.</p>
          @endforelse
        @endif
      </div>
    </div>

    {{-- ══════ 7. VM BERJALAN ══════ --}}
    <div class="col-12">
      <div class="card border rounded-4 overflow-hidden">
        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
          <h2 class="small fw-bold text-dark mb-0"><i class="fa-solid fa-desktop text-muted"></i> VM di Akun Ini</h2>
          <span class="badge badge-soft-secondary">{{ is_array($sections['vms']['data'] ?? null) ? count($sections['vms']['data']) : 0 }} VM</span>
        </div>
        @if ($sections['vms']['error'])
          <div class="p-4">{!! $err($sections['vms']['error']) !!}</div>
        @else
          @php
            $namaVm = collect($sections['vms']['data'] ?? [])->pluck('name')->filter();
            $kembar = $namaVm->duplicates()->unique()->values();
          @endphp

          @if ($kembar->isNotEmpty())
            <div class="px-4 py-3" style="background:#fef2f2;border-bottom:1px solid #fecaca">
              <p class="fw-semibold mb-1" style="font-size:13px;color:#b91c1c">
                <i class="fa-solid fa-triangle-exclamation"></i> Ditemukan VM dengan nama kembar!
              </p>
              <p class="mb-0" style="font-size:12px;color:#b91c1c">
                Nama: <b>{{ $kembar->implode(', ') }}</b> — kemungkinan terbentuk karena percobaan pembuatan
                yang timeout lalu diulang. <b>Setiap VM menagih biaya terpisah</b>, jadi hapus yang tidak
                terpakai lewat dashboard IDCloudHost. Cocokkan UUID di bawah dengan yang tercatat di menu Layanan VPS.
              </p>
            </div>
          @endif
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:13px">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                  <th class="px-4 py-3">Nama</th>
                  <th class="py-3">Spek</th>
                  <th class="py-3">OS</th>
                  <th class="py-3">IP</th>
                  <th class="py-3">Backup</th>
                  <th class="py-3">Status</th>
                  <th class="text-end px-4 py-3">Biaya/Jam</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($sections['vms']['data'] as $vm)
                  @php
                    $vmDisk = collect($vm['storage'] ?? [])->firstWhere('primary', true)['size'] ?? 0;
                    $vmSpec = [
                      'vcpu' => $vm['vcpu'] ?? 0, 'ram' => $vm['memory'] ?? 0, 'disk' => $vmDisk,
                      'os_name' => $vm['os_name'] ?? '', 'backup_enabled' => (bool) ($vm['backup'] ?? false), 'snapshot_gb' => 0,
                    ];
                    $vmRate = $rateEmpty ? null : \App\Services\Billing\HourlyRateCalculator::calculate($server, $vmSpec);
                  @endphp
                  <tr>
                    <td class="px-4 py-3">
                      <p class="fw-medium text-dark mb-0">{{ $vm['name'] ?? '—' }}</p>
                      <p class="text-muted mb-0" style="font-size:10px;font-family:monospace">{{ $vm['uuid'] ?? '' }}</p>
                    </td>
                    <td class="py-3 text-muted">{{ $vm['vcpu'] ?? '?' }} vCPU · {{ $vm['memory'] ?? '?' }} MB · {{ $vmDisk }} GB</td>
                    <td class="py-3 text-muted" style="font-size:11px">{{ $vm['os_name'] ?? '' }} {{ $vm['os_version'] ?? '' }}</td>
                    <td class="py-3 text-muted" style="font-family:monospace;font-size:11px">
                      {{ $vm['public_ipv4'] ?? $vm['private_ipv4'] ?? '—' }}
                    </td>
                    <td class="py-3">
                      <span class="badge {{ !empty($vm['backup']) ? 'badge-soft-success' : 'badge-soft-secondary' }}">
                        {{ !empty($vm['backup']) ? 'Aktif' : 'Off' }}
                      </span>
                    </td>
                    <td class="py-3">
                      <span class="badge {{ ($vm['status'] ?? '') === 'running' ? 'badge-soft-success' : 'badge-soft-secondary' }}">
                        {{ ucfirst($vm['status'] ?? '—') }}
                      </span>
                    </td>
                    <td class="text-end px-4 py-3 fw-semibold text-dark">
                      {{ $vmRate !== null ? 'Rp ' . number_format($vmRate, 2, ',', '.') : '—' }}
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted py-5">Belum ada VM di akun ini.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    {{-- ══════ PEMAKAIAN BULAN BERJALAN ══════ --}}
    <div class="col-12">
      <div class="card border rounded-4 overflow-hidden">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-0"><i class="fa-solid fa-chart-line text-muted"></i> Biaya Modal Bulan Berjalan (dari IDCloudHost)</h2>
          <p class="text-muted mt-1 mb-0" style="font-size:11px">
            Ini yang IDCloudHost tagihkan ke KAMU — bandingkan dengan pendapatan dari klien untuk tahu untung/rugi sesungguhnya.
          </p>
        </div>
        @if ($sections['usage']['error'])
          <div class="p-4">{!! $err($sections['usage']['error']) !!}</div>
        @else
          @php $totalCost = collect($sections['usage']['data'] ?? [])->sum(fn ($u) => (float) ($u['cost'] ?? 0)); @endphp
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:13px">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                  <th class="px-4 py-2">Resource</th>
                  <th class="text-end py-2">Jam</th>
                  <th class="text-end py-2">Harga/Jam</th>
                  <th class="text-end px-4 py-2">Biaya</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($sections['usage']['data'] as $usage)
                  <tr>
                    <td class="px-4 py-2 text-dark">{{ $usage['description'] ?? '—' }}</td>
                    <td class="text-end py-2 text-muted">{{ number_format((float) ($usage['hours'] ?? 0), 1) }}</td>
                    <td class="text-end py-2 text-muted">{{ number_format((float) ($usage['price'] ?? 0), 5) }}</td>
                    <td class="text-end px-4 py-2 fw-medium text-dark">{{ number_format((float) ($usage['cost'] ?? 0), 5) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pemakaian tercatat bulan ini.</td></tr>
                @endforelse
              </tbody>
              @if ($totalCost > 0)
                <tfoot>
                  <tr style="background:#f8fafc">
                    <td colspan="3" class="px-4 py-2 text-end fw-bold text-dark">Total Modal Bulan Ini</td>
                    <td class="text-end px-4 py-2 fw-bold text-dark">{{ number_format($totalCost, 4) }}</td>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
        @endif
      </div>
    </div>

    {{-- ══════ 8. DISK, IP, NETWORK ══════ --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-hard-drive text-muted"></i> Block Storage</h2>
        @if ($sections['disks']['error'])
          {!! $err($sections['disks']['error']) !!}
        @else
          @forelse ($sections['disks']['data'] as $disk)
            <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2 mb-2">
              <div class="min-w-0">
                <p class="fw-medium text-dark text-truncate mb-0" style="font-size:13px">{{ $disk['display_name'] ?? '(tanpa nama)' }}</p>
                <p class="text-muted mb-0" style="font-size:10px">{{ count($disk['snapshots'] ?? []) }} snapshot</p>
              </div>
              <span class="badge badge-soft-secondary text-nowrap">{{ $disk['size_gb'] ?? '?' }} GB</span>
            </div>
          @empty
            <p class="text-muted mb-0" style="font-size:13px">Tidak ada disk terpisah.</p>
          @endforelse
        @endif
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-network-wired text-muted"></i> Floating IP</h2>
        @if ($sections['ips']['error'])
          {!! $err($sections['ips']['error']) !!}
        @else
          @forelse ($sections['ips']['data'] as $ip)
            <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2 mb-2">
              <p class="fw-medium text-dark mb-0" style="font-size:13px;font-family:monospace">{{ $ip['address'] ?? '—' }}</p>
              <span class="badge {{ !empty($ip['assigned_to']) ? 'badge-soft-success' : 'badge-soft-warning' }}">
                {{ !empty($ip['assigned_to']) ? 'Terpakai' : 'Nganggur' }}
              </span>
            </div>
          @empty
            <p class="text-muted mb-0" style="font-size:13px">Tidak ada floating IP.</p>
          @endforelse
        @endif
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3"><i class="fa-solid fa-diagram-project text-muted"></i> Private Network</h2>
        @if ($sections['networks']['error'])
          {!! $err($sections['networks']['error']) !!}
        @else
          @forelse ($sections['networks']['data'] as $net)
            <div class="rounded-3 border px-3 py-2 mb-2">
              <div class="d-flex align-items-center gap-2">
                <p class="fw-medium text-dark mb-0" style="font-size:13px">{{ $net['name'] ?? '—' }}</p>
                @if (!empty($net['is_default']))
                  <span class="badge badge-soft-success">Default</span>
                @endif
              </div>
              <p class="text-muted mb-0" style="font-size:11px;font-family:monospace">{{ $net['subnet'] ?? '' }}</p>
              <p class="text-muted mb-0" style="font-size:10px">{{ $net['resources_count'] ?? 0 }} resource</p>
            </div>
          @empty
            <p class="text-muted mb-0" style="font-size:13px">Tidak ada private network.</p>
          @endforelse
        @endif
      </div>
    </div>
  </div>

@endsection
