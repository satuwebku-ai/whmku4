@extends('layouts.admin')

@section('title', $server->exists ? 'Edit Server' : 'Tambah Server')

@section('content')

  @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $server->exists ? 'Edit Server' : 'Tambah Server' }}</h1>
    <p class="small text-muted mb-0">Kredensial API dienkripsi otomatis di database (pakai APP_KEY).</p>
  </div>

  <div id="hintCpanel" class="provider-hint-server rounded-3 mb-3 px-3 py-2 small" style="max-width:42rem;background:#eef2ff;border:1px solid #c7d2fe;color:#4338ca">
    <i class="fa-solid fa-circle-info"></i>
    Untuk cPanel/WHM, buat API Token dari <b>WHM » Development » Manage API Tokens</b> —
    jangan pakai password root langsung. Port default WHM adalah <b>2087</b>.
  </div>

  <div id="hintIdcloudhost" class="provider-hint-server d-none rounded-3 mb-3 px-3 py-2 small" style="max-width:42rem;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534">
    <i class="fa-solid fa-cloud"></i>
    <b>IDCloudHost</b> menyediakan VM/VPS baru langsung lewat API, bukan panel di server yang sudah ada.
    Ambil API Key dari <a href="https://app.idcloudhost.com" target="_blank" class="text-decoration-underline" style="color:inherit">app.idcloudhost.com</a> » API Token,
    tempel di kolom <b>API Token</b> di bawah. Kolom <b>Hostname</b> diisi <b>slug lokasi</b> (opsional — mis. <code>jkt01</code>, <code>jkt02</code>, <code>jkt03</code>, <code>sgp01</code>;
    kosongkan untuk lokasi default akun). Kolom <b>API Username</b> diisi <b>Billing Account ID</b> berupa <b>ANGKA</b>
    (mis. <code>1200206137</code> — lihat di halaman Diagnosa); kosongkan untuk memakai akun billing default. Isian selain angka diabaikan.
  </div>

  <form method="POST" action="{{ $server->exists ? route('admin.servers.update', $server) : route('admin.servers.store') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($server->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama / Label Server</label>
        <input type="text" name="name" value="{{ old('name', $server->name) }}" placeholder="Server JKT-01" class="form-control form-control-sm" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Jenis Panel</label>
        <select name="panel" id="panelSelect" class="form-select" style="{{ $selectStyle }}">
          <option value="cpanel" @selected(old('panel', $server->panel ?? 'cpanel') === 'cpanel')>cPanel / WHM</option>
          <option value="directadmin" @selected(old('panel', $server->panel) === 'directadmin')>DirectAdmin (segera)</option>
          <option value="plesk" @selected(old('panel', $server->panel) === 'plesk')>Plesk (segera)</option>
          <option value="idcloudhost" @selected(old('panel', $server->panel) === 'idcloudhost')>IDCloudHost (Jual VM/VPS)</option>
        </select>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-8">
        <label class="form-label small fw-medium text-dark" id="labelHostname">Hostname / IP</label>
        <input type="text" name="hostname" id="fieldHostname" value="{{ old('hostname', $server->hostname) }}" placeholder="server1.contoh.com" class="form-control form-control-sm">
        @error('hostname') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-4" id="fieldPortWrap">
        <label class="form-label small fw-medium text-dark">Port</label>
        <input type="number" name="port" value="{{ old('port', $server->port ?? 2087) }}" class="form-control form-control-sm" required>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nameserver 1</label>
        <input type="text" name="ns1" value="{{ old('ns1', $server->ns1) }}" placeholder="ns1.satucloudhosting.com" class="form-control form-control-sm">
        @error('ns1') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nameserver 2</label>
        <input type="text" name="ns2" value="{{ old('ns2', $server->ns2) }}" placeholder="ns2.satucloudhosting.com" class="form-control form-control-sm">
        @error('ns2') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <p class="text-muted mb-0" style="font-size:11px">
        Kalau diisi, domain otomatis diarahkan ke nameserver ini begitu klien membeli hosting di server ini untuk domain yang sudah terdaftar lewat sistem kita.
      </p>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark" id="labelApiUsername">API Username</label>
        <input type="text" name="api_username" id="fieldApiUsername" value="{{ old('api_username', $server->api_username) }}" placeholder="root" class="form-control form-control-sm">
        @error('api_username') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">API Token {{ $server->exists ? '(kosongkan jika tidak diganti)' : '' }}</label>
        <input type="password" name="api_token" placeholder="{{ $server->exists ? '••••••••••••' : '' }}" class="form-control form-control-sm" {{ $server->exists ? '' : 'required' }}>
        @error('api_token') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3 align-items-center">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Kapasitas Maks. Akun (opsional)</label>
        <input type="number" name="max_accounts" value="{{ old('max_accounts', $server->max_accounts) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-6 d-flex align-items-center gap-4">
        <label class="d-flex align-items-center gap-2 small text-dark mb-0">
          <input type="checkbox" name="verify_ssl" value="1" @checked(old('verify_ssl', $server->verify_ssl ?? true)) class="form-check-input" style="margin-top:0">
          Verifikasi SSL
        </label>
        <label class="d-flex align-items-center gap-2 small text-dark mb-0">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $server->is_active ?? true)) class="form-check-input" style="margin-top:0">
          Aktif
        </label>
      </div>
    </div>

    <div id="rateCardSection" class="d-none pt-3 mt-3 border-top">
      <p class="fw-bold text-muted mb-1" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
        <i class="fa-solid fa-tags"></i> Kartu Harga (per jam)
      </p>
      <p class="text-muted mb-3" style="font-size:11px">
        Dipakai otomatis menghitung tagihan per jam untuk semua VM di server ini (lihat cron
        <code>lumora:charge-hourly-usage</code>) — kosongkan komponen yang tidak berlaku.
      </p>

      <div class="row g-2 mb-3">
        @foreach (['manual' => 'Isi Manual', 'markup' => 'Markup % dari Harga Modal'] as $mKey => $mLabel)
          @php $activeMode = old('pricing_mode', $server->pricing_mode ?? 'manual') === $mKey; @endphp
          <div class="col-6">
            <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100"
                   style="cursor:pointer;{{ $activeMode ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
              <input type="radio" name="pricing_mode" value="{{ $mKey }}" @checked($activeMode) class="d-none" data-pricing-mode>
              {{ $mLabel }}
            </label>
          </div>
        @endforeach
      </div>

      <div id="markupFields" class="{{ old('pricing_mode', $server->pricing_mode ?? 'manual') === 'markup' ? '' : 'd-none' }} rounded-3 border p-3 mb-3" style="background:#f8fafc">
        <div class="row g-3 align-items-end">
          <div class="col-sm-5">
            <label class="form-label small fw-medium text-dark">Markup (%)</label>
            <input type="number" step="0.01" min="0" name="markup_percent" value="{{ old('markup_percent', $server->markup_percent ?? 50) }}" class="form-control form-control-sm">
            <p class="text-muted mt-1 mb-0" style="font-size:10px">Mis. 50 = jual 1,5× harga modal</p>
          </div>
          <div class="col-sm-7">
            @if ($server->exists && $server->cost_cached_at)
              <p class="text-muted mb-1" style="font-size:11px">
                Harga modal tersimpan {{ $server->cost_cached_at->diffForHumans() }}:
                vCPU {{ number_format((float) ($server->cost_cache['vcpu'] ?? 0), 3) }} ·
                RAM {{ number_format((float) ($server->cost_cache['ram'] ?? 0), 3) }} ·
                Disk {{ number_format((float) ($server->cost_cache['storage'] ?? 0), 3) }}
              </p>
            @else
              <p class="mb-1" style="font-size:11px;color:#b45309">
                <i class="fa-solid fa-triangle-exclamation"></i> Harga modal belum pernah ditarik — mode markup belum bisa menghitung.
              </p>
            @endif
            @if ($server->exists)
              <button type="button" onclick="document.getElementById('syncCostForm').submit()" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-cloud-arrow-down" style="font-size:11px"></i> Tarik Harga Modal Sekarang
              </button>
            @else
              <p class="text-muted mb-0" style="font-size:11px">Simpan server dulu, baru harga modal bisa ditarik.</p>
            @endif
          </div>
        </div>

        {{-- Pratinjau: berapa harga modal, jadi berapa setelah markup,
             dan berapa totalnya per bulan untuk contoh VPS. Angka ini
             cuma pemberitahuan -- tidak disimpan ke mana pun. --}}
        @if ($server->exists && $server->cost_cached_at && is_array($server->cost_cache))
          <div class="mt-3 pt-3 border-top">
            <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
              Pratinjau Harga Jual
            </p>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-2" style="font-size:12px">
                <thead>
                  <tr class="text-uppercase text-muted" style="background:#fff;font-size:10px">
                    <th class="py-1">Komponen</th>
                    <th class="text-end py-1">Modal / jam</th>
                    <th class="text-end py-1">Jual / jam</th>
                    <th class="text-end py-1">Untung / jam</th>
                  </tr>
                </thead>
                <tbody id="previewRows"></tbody>
              </table>
            </div>

            <div class="rounded-3 p-3" style="background:#fff;border:1px solid #e2e8f0">
              <p class="text-muted mb-2" style="font-size:11px">
                Contoh VPS <b id="sampleLabel">2 vCPU / 2 GB / 40 GB</b> — ubah untuk melihat perbandingan lain:
              </p>
              <div class="row g-2 mb-3">
                <div class="col-4">
                  <input type="number" id="pvCpu" value="2" min="1" class="form-control form-control-sm" placeholder="vCPU">
                </div>
                <div class="col-4">
                  <input type="number" id="pvRam" value="2048" step="512" min="512" class="form-control form-control-sm" placeholder="RAM MB">
                </div>
                <div class="col-4">
                  <input type="number" id="pvDisk" value="40" min="20" class="form-control form-control-sm" placeholder="Disk GB">
                </div>
              </div>
              <div class="row g-2 text-center">
                <div class="col-4">
                  <p class="text-muted mb-0" style="font-size:10px">Modal / bulan</p>
                  <p class="fw-semibold text-dark mb-0" id="sumCost">—</p>
                </div>
                <div class="col-4">
                  <p class="text-muted mb-0" style="font-size:10px">Jual / bulan</p>
                  <p class="fw-bold mb-0" id="sumSell" style="color:#4338ca">—</p>
                </div>
                <div class="col-4">
                  <p class="text-muted mb-0" style="font-size:10px">Untung / bulan</p>
                  <p class="fw-semibold mb-0" id="sumProfit" style="color:#047857">—</p>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0" style="font-size:10px">Hitungan 730 jam/bulan. Angka ini hanya pemberitahuan, tidak disimpan.</p>
            </div>
          </div>

          <script>
            (function () {
              const cost = @json($server->cost_cache);
              const labels = { vcpu: 'vCPU (per unit)', ram: 'RAM (per GB)', storage: 'Storage (per GB)', backup: 'Backup (per GB)', snapshot: 'Snapshot (per GB)', windows: 'Lisensi Windows' };
              const rupiah = (n) => 'Rp ' + n.toLocaleString('id-ID', { maximumFractionDigits: 2 });

              function markup() {
                return 1 + ((parseFloat(document.querySelector('[name="markup_percent"]')?.value) || 0) / 100);
              }

              function render() {
                const f = markup();
                const rows = Object.keys(labels).map(function (k) {
                  const c = parseFloat(cost[k] || 0);
                  if (c <= 0) return '';
                  const jual = c * f;
                  return '<tr><td class="py-1 text-dark">' + labels[k] + '</td>'
                    + '<td class="text-end py-1 text-muted">' + rupiah(c) + '</td>'
                    + '<td class="text-end py-1 fw-medium text-dark">' + rupiah(jual) + '</td>'
                    + '<td class="text-end py-1" style="color:#047857">+' + rupiah(jual - c) + '</td></tr>';
                }).join('');

                document.getElementById('previewRows').innerHTML = rows || '<tr><td colspan="4" class="text-center text-muted py-2">Harga modal kosong.</td></tr>';

                const vcpu = parseFloat(document.getElementById('pvCpu').value) || 0;
                const ramGb = (parseFloat(document.getElementById('pvRam').value) || 0) / 1024;
                const disk = parseFloat(document.getElementById('pvDisk').value) || 0;

                const modalJam = vcpu * (cost.vcpu || 0) + ramGb * (cost.ram || 0) + disk * (cost.storage || 0);
                const jualJam = modalJam * f;

                document.getElementById('sampleLabel').textContent =
                  vcpu + ' vCPU / ' + (ramGb % 1 === 0 ? ramGb : ramGb.toFixed(1)) + ' GB / ' + disk + ' GB';
                document.getElementById('sumCost').textContent = rupiah(modalJam * 730);
                document.getElementById('sumSell').textContent = rupiah(jualJam * 730);
                document.getElementById('sumProfit').textContent = '+' + rupiah((jualJam - modalJam) * 730);
              }

              ['pvCpu', 'pvRam', 'pvDisk'].forEach(id => document.getElementById(id).addEventListener('input', render));
              document.querySelector('[name="markup_percent"]')?.addEventListener('input', render);
              render();
            })();
          </script>
        @endif
      </div>

      <div id="manualRateFields" class="{{ old('pricing_mode', $server->pricing_mode ?? 'manual') === 'markup' ? 'd-none' : '' }}">
      <div class="row g-3">
        <div class="col-sm-6 col-lg-4">
          <label class="form-label small fw-medium text-dark">Harga per vCPU</label>
          <input type="number" step="0.000001" min="0" name="price_per_vcpu_hour" value="{{ old('price_per_vcpu_hour', $server->price_per_vcpu_hour) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6 col-lg-4">
          <label class="form-label small fw-medium text-dark">Harga per GB RAM</label>
          <input type="number" step="0.000001" min="0" name="price_per_ram_gb_hour" value="{{ old('price_per_ram_gb_hour', $server->price_per_ram_gb_hour) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6 col-lg-4">
          <label class="form-label small fw-medium text-dark">Harga per GB Storage</label>
          <input type="number" step="0.000001" min="0" name="price_per_storage_gb_hour" value="{{ old('price_per_storage_gb_hour', $server->price_per_storage_gb_hour) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6 col-lg-4">
          <label class="form-label small fw-medium text-dark">Harga per GB Backup</label>
          <input type="number" step="0.000001" min="0" name="price_per_backup_gb_hour" value="{{ old('price_per_backup_gb_hour', $server->price_per_backup_gb_hour) }}" class="form-control form-control-sm">
          <p class="text-muted mt-1 mb-0" style="font-size:10px">Berlaku dikali ukuran disk, hanya kalau backup diaktifkan di VM.</p>
        </div>
        <div class="col-sm-6 col-lg-4">
          <label class="form-label small fw-medium text-dark">Harga per GB Snapshot</label>
          <input type="number" step="0.000001" min="0" name="price_per_snapshot_gb_hour" value="{{ old('price_per_snapshot_gb_hour', $server->price_per_snapshot_gb_hour) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6 col-lg-4">
          <label class="form-label small fw-medium text-dark">Lisensi Windows per vCPU</label>
          <input type="number" step="0.000001" min="0" name="price_windows_license_per_vcpu_hour" value="{{ old('price_windows_license_per_vcpu_hour', $server->price_windows_license_per_vcpu_hour) }}" class="form-control form-control-sm">
          <p class="text-muted mt-1 mb-0" style="font-size:10px">Hanya berlaku kalau OS mengandung kata "windows".</p>
        </div>
      </div>
      </div>{{-- /#manualRateFields --}}
    </div>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.servers.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

  @if ($server->exists)
    {{-- Form terpisah di luar form utama -- HTML tidak mengizinkan form bersarang. --}}
    <form id="syncCostForm" method="POST" action="{{ route('admin.servers.sync-cost', $server) }}" class="d-none">
      @csrf
    </form>
  @endif

  <script>
    (function () {
      const radios = document.querySelectorAll('[data-pricing-mode]');
      const markupBox = document.getElementById('markupFields');
      const manualBox = document.getElementById('manualRateFields');

      if (! radios.length) return;

      function sync() {
        const mode = document.querySelector('[data-pricing-mode]:checked')?.value;
        markupBox.classList.toggle('d-none', mode !== 'markup');
        manualBox.classList.toggle('d-none', mode === 'markup');

        radios.forEach(function (r) {
          const label = r.closest('label');
          const on = r.checked;
          label.style.borderColor = on ? '#4f46e5' : '';
          label.style.background = on ? 'rgba(79,70,229,.06)' : '';
          label.style.color = on ? '#4338ca' : '';
        });
      }

      radios.forEach(r => r.addEventListener('change', sync));
      sync();
    })();
  </script>

  <script>
    (function () {
      const select   = document.getElementById('panelSelect');
      const hostname = document.getElementById('fieldHostname');
      const apiUser  = document.getElementById('fieldApiUsername');
      const labelHost = document.getElementById('labelHostname');
      const labelUser = document.getElementById('labelApiUsername');
      const portWrap  = document.getElementById('fieldPortWrap');

      function sync() {
        const isIdch = select.value === 'idcloudhost';

        document.querySelectorAll('.provider-hint-server').forEach(el => el.classList.add('d-none'));
        document.getElementById(isIdch ? 'hintIdcloudhost' : 'hintCpanel').classList.remove('d-none');

        hostname.required = !isIdch;
        apiUser.required = !isIdch;
        portWrap.classList.toggle('d-none', isIdch);
        document.getElementById('rateCardSection').classList.toggle('d-none', !isIdch);

        labelHost.textContent = isIdch ? 'Slug Lokasi (opsional)' : 'Hostname / IP';
        hostname.placeholder = isIdch ? 'jkt01 (kosongkan utk default)' : 'server1.contoh.com';

        labelUser.textContent = isIdch ? 'Billing Account ID — angka saja (opsional)' : 'API Username';
        apiUser.placeholder = isIdch ? 'mis. 1200206137 — kosongkan utk default' : 'root';
      }

      select.addEventListener('change', sync);
      sync();
    })();
  </script>

@endsection
