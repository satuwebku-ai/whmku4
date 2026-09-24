@extends('layouts.admin')

@section('title', $server->exists ? 'Edit Server' : 'Tambah Server')

@section('content')

  @php
    $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem';
    $currentPanel = old('panel', $server->panel ?: 'cpanel');
    $currentVps   = old('vps_provider', $server->vps_provider);

    // Profil tampilan per JENIS PANEL hosting biasa. Hanya field yang ada
    // di 'fields' yang tampil (Port & Nameserver lewat 'port' / 'ns').
    $panelProfiles = [
      'cpanel' => [
        'tone' => 'primary', 'icon' => 'fa-circle-info', 'port' => 2087, 'ns' => true, 'rateCard' => false,
        'hint' => 'Untuk cPanel/WHM, buat API Token dari <b>WHM » Development » Manage API Tokens</b> — jangan pakai password root langsung. Port default WHM adalah <b>2087</b>.',
        'fields' => [
          'hostname' => ['label' => 'Hostname / IP', 'placeholder' => 'server1.contoh.com', 'required' => true],
          'api_username' => ['label' => 'API Username', 'placeholder' => 'root', 'required' => true],
          'api_token' => ['label' => 'API Token'],
        ],
      ],
      'directadmin' => [
        'tone' => 'warning', 'icon' => 'fa-triangle-exclamation', 'port' => 2222, 'ns' => true, 'rateCard' => false,
        'hint' => '<b>DirectAdmin belum tersedia</b> — form ini bisa disimpan untuk didata lebih dulu, tapi provisioning otomatis belum jalan untuk panel ini. Kalau nanti diaktifkan, autentikasi memakai <b>Login Key</b> (dibuat dari DirectAdmin » Login Keys), bukan password akun admin — tempel di kolom <b>Login Key (Token)</b>. Port default DirectAdmin adalah <b>2222</b>.',
        'fields' => [
          'hostname' => ['label' => 'Hostname / IP', 'placeholder' => 'server1.contoh.com', 'required' => true],
          'api_username' => ['label' => 'Admin Username', 'placeholder' => 'admin', 'required' => true],
          'api_token' => ['label' => 'Login Key (Token)'],
        ],
      ],
      'plesk' => [
        'tone' => 'warning', 'icon' => 'fa-triangle-exclamation', 'port' => 8443, 'ns' => true, 'rateCard' => false,
        'hint' => '<b>Plesk belum tersedia</b> — form ini bisa disimpan untuk didata lebih dulu, tapi provisioning otomatis belum jalan untuk panel ini. Kalau nanti diaktifkan, autentikasi memakai <b>API Key</b> (dibuat dari Plesk » Tools &amp; Settings » API Keys) — tempel di kolom <b>API Key</b>. Port default Plesk adalah <b>8443</b>.',
        'fields' => [
          'hostname' => ['label' => 'Hostname / IP', 'placeholder' => 'server1.contoh.com', 'required' => true],
          'api_username' => ['label' => 'Admin Username', 'placeholder' => 'admin', 'required' => true],
          'api_token' => ['label' => 'API Key'],
        ],
      ],
    ];

    // Profil tampilan per VPS PROVIDER -- dibaca dari config/vps_providers.php,
    // jadi provider baru muncul di form ini tanpa mengubah file ini.
    $vpsProfiles = collect(config('vps_providers', []))->map(fn ($cfg) => [
      'tone' => $cfg['tone'] ?? 'info', 'icon' => 'fa-cloud', 'port' => null, 'ns' => false,
      'rateCard' => (bool) ($cfg['cost_sync'] ?? false),
      'currency' => strtoupper($cfg['currency'] ?? 'IDR'),
      'hint' => $cfg['hint'] ?? '',
      'fields' => $cfg['fields'] ?? [],
    ])->all();
  @endphp

  <style>
    .srv-hint { border: 1px solid; }
    .srv-hint.tone-primary   { background:#eef2ff; border-color:#c7d2fe; color:#4338ca; }
    .srv-hint.tone-warning   { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
    .srv-hint.tone-info      { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }
    .srv-hint.tone-success   { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
    .srv-hint.tone-secondary { background:#f8fafc; border-color:#e2e8f0; color:#475569; }
  </style>

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $server->exists ? 'Edit Server' : 'Tambah Server' }}</h1>
    <p class="small text-muted mb-0">Kredensial API dienkripsi otomatis di database (pakai APP_KEY).</p>
  </div>

  {{-- Isi & warna kotak ini diganti JS sesuai Jenis Panel / VPS Provider yang dipilih. --}}
  <div id="serverHint" class="srv-hint tone-primary rounded-3 mb-3 px-3 py-2 small" style="max-width:42rem"></div>

  <form method="POST" action="{{ $server->exists ? route('admin.servers.update', $server) : route('admin.servers.store') }}" class="card border rounded-4 p-4" style="max-width:42rem" autocomplete="off">
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
          <option value="cpanel" @selected($currentPanel === 'cpanel')>cPanel / WHM</option>
          <option value="directadmin" @selected($currentPanel === 'directadmin')>DirectAdmin (segera)</option>
          <option value="plesk" @selected($currentPanel === 'plesk')>Plesk (segera)</option>
          <option value="vps" @selected($currentPanel === 'vps')>VM / VPS (Cloud)</option>
        </select>
        @error('panel') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    {{-- Hanya tampil kalau Jenis Panel = VM / VPS. --}}
    <div id="vpsProviderWrap" class="mb-3 d-none">
      <label class="form-label small fw-medium text-dark">VPS Provider</label>
      <select name="vps_provider" id="vpsProviderSelect" class="form-select" style="{{ $selectStyle }}" disabled>
        <option value="">— Pilih provider —</option>
        @foreach (\App\Services\Vps\VpsProviderFactory::supported() as $key => $label)
          <option value="{{ $key }}" @selected($currentVps === $key)>{{ $label }}</option>
        @endforeach
      </select>
      <p class="text-muted mb-0 mt-1" style="font-size:11px">Provider tempat VM/VPS dibuat lewat API. Kolom di bawah menyesuaikan provider yang dipilih.</p>
      @error('vps_provider') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="row g-3 mb-3" id="rowHost">
      <div class="col-sm-8" id="wrapHostname">
        <label class="form-label small fw-medium text-dark" id="labelHostname">Hostname / IP</label>
        <input type="text" name="hostname" id="fieldHostname" value="{{ old('hostname', $server->hostname) }}" class="form-control form-control-sm" autocomplete="off">
        @error('hostname') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-4" id="wrapPort">
        <label class="form-label small fw-medium text-dark">Port</label>
        <input type="number" name="port" id="fieldPort" value="{{ old('port', $server->port ?? 2087) }}" class="form-control form-control-sm">
        @error('port') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3" id="nameserverRow">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nameserver 1</label>
        <input type="text" name="ns1" value="{{ old('ns1', $server->ns1) }}" placeholder="ns1.satucloudhosting.com" class="form-control form-control-sm" autocomplete="off">
        @error('ns1') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nameserver 2</label>
        <input type="text" name="ns2" value="{{ old('ns2', $server->ns2) }}" placeholder="ns2.satucloudhosting.com" class="form-control form-control-sm" autocomplete="off">
        @error('ns2') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <p class="text-muted mb-0" style="font-size:11px">
        Kalau diisi, domain otomatis diarahkan ke nameserver ini begitu klien membeli hosting di server ini untuk domain yang sudah terdaftar lewat sistem kita.
      </p>
    </div>

    <div class="row g-3 mb-3" id="rowCredentials">
      <div class="col-sm-6" id="wrapApiUser">
        <label class="form-label small fw-medium text-dark" id="labelApiUsername">API Username</label>
        <input type="text" name="api_username" id="fieldApiUsername" value="{{ old('api_username', $server->api_username) }}" class="form-control form-control-sm" autocomplete="off">
        @error('api_username') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6" id="wrapApiToken">
        <label class="form-label small fw-medium text-dark" id="labelApiToken">API Token</label>
        <input type="password" name="api_token" id="fieldApiToken" class="form-control form-control-sm" autocomplete="new-password">
        @error('api_token') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3 align-items-center" id="rowGeneral">
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
        <i class="fa-solid fa-coins"></i> Harga Modal (dari Provider)
      </p>
      <p class="text-muted mb-3" style="font-size:11px">
        <b>Harga jual per jam diatur di halaman Produk</b> (Penjualan → Produk → pilih kategori VPS → "Potong Saldo
        per Jam") — bukan di sini lagi, supaya produk yang beda boleh punya harga beda walau jalan di server yang
        sama. Yang tersisa di sini cuma <b>harga modal</b> dari provider — dipakai sebagai basis hitungan kalau ada
        produk yang pakai mode "Markup % dari Harga Modal".
      </p>

      {{-- Kurs ke Rupiah: hanya untuk provider yang harga modalnya bukan IDR (mis. DigitalOcean = USD). --}}
      <div id="wrapFx" class="mb-3 d-none" style="max-width:20rem">
        <label class="form-label small fw-medium text-dark">Kurs: 1 <span id="fxCurrency">USD</span> = Rp</label>
        <input type="number" step="0.01" min="0" name="cost_fx_rate" id="fieldFx" value="{{ old('cost_fx_rate', $server->cost_fx_rate ? (float) $server->cost_fx_rate : '') }}" placeholder="mis. 16500" class="form-control form-control-sm" disabled>
        @error('cost_fx_rate') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Diisi manual — sistem tidak menebak kurs. Tanpa kurs, harga modal provider ini belum bisa dipakai menghitung tarif.</p>
      </div>

      @if ($server->exists && $server->cost_cached_at)
        <p class="text-muted mb-2" style="font-size:11px">
          Harga modal tersimpan {{ $server->cost_cached_at->diffForHumans() }}:
          @if ($server->costModel() === 'size')
            {{ count($server->cost_cache['sizes'] ?? []) }} size · {{ $server->costCurrency() }}
            @if ($server->costFxRate() <= 0)
              <span style="color:#b45309">· kurs ke Rupiah belum diisi</span>
            @endif
          @else
            vCPU {{ number_format((float) ($server->cost_cache['vcpu'] ?? 0), 3) }} ·
            RAM {{ number_format((float) ($server->cost_cache['ram'] ?? 0), 3) }} ·
            Disk {{ number_format((float) ($server->cost_cache['storage'] ?? 0), 3) }}
          @endif
        </p>
      @else
        <p class="mb-2" style="font-size:11px;color:#b45309">
          <i class="fa-solid fa-triangle-exclamation"></i> Harga modal belum pernah ditarik — mode markup di
          halaman Produk belum bisa menghitung sampai ini ditarik.
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
      const $ = (id) => document.getElementById(id);
      const panelSelect = $('panelSelect');
      const vpsSelect   = $('vpsProviderSelect');
      const isEdit      = @json($server->exists);
      const panels      = @json($panelProfiles);
      const providers   = @json($vpsProfiles);

      const hint = $('serverHint');
      const el = {
        hostname: $('fieldHostname'), apiUser: $('fieldApiUsername'), apiToken: $('fieldApiToken'), port: $('fieldPort'),
        ns: $('nameserverRow').querySelectorAll('input'),
        labelHost: $('labelHostname'), labelUser: $('labelApiUsername'), labelToken: $('labelApiToken'),
        wrapHost: $('wrapHostname'), wrapPort: $('wrapPort'), wrapUser: $('wrapApiUser'), wrapToken: $('wrapApiToken'),
        rowHost: $('rowHost'), rowNs: $('nameserverRow'), rowCred: $('rowCredentials'),
        rateCard: $('rateCardSection'), vpsWrap: $('vpsProviderWrap'),
        wrapFx: $('wrapFx'), fxCurrency: $('fxCurrency'),
      };

      // Port bawaan tiap panel hosting: hanya menimpa kalau kosong / masih
      // berisi port bawaan panel lain, supaya port kustom admin tidak hilang.
      const knownDefaultPorts = ['2087', '2222', '8443'];

      // Elemen yang disembunyikan ikut di-disable supaya tidak terkirim
      // saat submit (dan tidak memblokir submit lewat atribut required).
      function show(node, on, inputs) {
        node.classList.toggle('d-none', !on);
        (inputs || node.querySelectorAll('input,select')).forEach((i) => { i.disabled = !on; });
      }

      // null = Jenis Panel "VM / VPS" tapi provider belum dipilih.
      function currentProfile() {
        if (panelSelect.value === 'vps') {
          return providers[vpsSelect.value] || null;
        }
        return panels[panelSelect.value] || panels.cpanel;
      }

      function setHint(cfg) {
        if (cfg) {
          hint.className = 'srv-hint tone-' + cfg.tone + ' rounded-3 mb-3 px-3 py-2 small';
          hint.innerHTML = '<i class="fa-solid ' + cfg.icon + '"></i> ' + cfg.hint;
        } else {
          hint.className = 'srv-hint tone-secondary rounded-3 mb-3 px-3 py-2 small';
          hint.innerHTML = '<i class="fa-solid fa-cloud"></i> <b>VM / VPS</b> — server cloud yang menjual VM/VPS lewat API provider. Pilih <b>VPS Provider</b> di bawah untuk menampilkan pengaturan dan panduan pengisiannya.';
        }
      }

      function sync() {
        const isVps = panelSelect.value === 'vps';
        const cfg = currentProfile();
        const f = cfg ? cfg.fields : {};

        show(el.vpsWrap, isVps);
        vpsSelect.required = isVps;
        setHint(cfg);

        // Hostname & Port
        show(el.wrapHost, !!f.hostname);
        show(el.wrapPort, !!(cfg && cfg.port));
        el.wrapHost.classList.toggle('col-sm-8', !!(cfg && cfg.port));
        el.wrapHost.classList.toggle('col-12', !(cfg && cfg.port));
        el.rowHost.classList.toggle('d-none', !(f.hostname || (cfg && cfg.port)));
        if (f.hostname) {
          el.labelHost.textContent = f.hostname.label;
          el.hostname.placeholder = f.hostname.placeholder || '';
          el.hostname.required = !!f.hostname.required;
        }
        el.port.required = !!(cfg && cfg.port);
        if (cfg && cfg.port && (!el.port.value || knownDefaultPorts.includes(el.port.value))) {
          el.port.value = cfg.port;
        }

        // Nameserver (hanya panel hosting biasa)
        show(el.rowNs, !!(cfg && cfg.ns), el.ns);

        // API Username & Token
        show(el.wrapUser, !!f.api_username);
        show(el.wrapToken, !!f.api_token);
        el.wrapToken.classList.toggle('col-sm-6', !!f.api_username);
        el.wrapToken.classList.toggle('col-12', !f.api_username);
        el.rowCred.classList.toggle('d-none', !(f.api_username || f.api_token));
        if (f.api_username) {
          el.labelUser.textContent = f.api_username.label;
          el.apiUser.placeholder = f.api_username.placeholder || '';
          el.apiUser.required = !!f.api_username.required;
        }
        if (f.api_token) {
          el.labelToken.textContent = f.api_token.label + (isEdit ? ' (kosongkan jika tidak diganti)' : '');
          el.apiToken.placeholder = isEdit ? '••••••••••••' : '';
          el.apiToken.required = !isEdit;
        }

        // Harga modal (hanya provider yang mendukung tarik harga)
        show(el.rateCard, !!(cfg && cfg.rateCard), []);

        // Kurs hanya untuk provider yang harga modalnya bukan Rupiah
        const needsFx = !!(cfg && cfg.rateCard && cfg.currency && cfg.currency !== 'IDR');
        show(el.wrapFx, needsFx);
        if (needsFx) el.fxCurrency.textContent = cfg.currency;
      }

      panelSelect.addEventListener('change', sync);
      vpsSelect.addEventListener('change', sync);
      sync();
    })();
  </script>

@endsection
