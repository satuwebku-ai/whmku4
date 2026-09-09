@extends('layouts.admin')

@section('title', $account->exists ? 'Edit Hosting Account' : 'Buat Hosting Account')

@section('content')

  @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $account->exists ? 'Edit Hosting Account' : 'Buat Hosting Account' }}</h1>
    <p class="small text-muted mb-0">
      @if ($account->exists && $account->provision_message)
        Status provisioning terakhir:
        <span class="fw-medium {{ $account->provision_status === 'provisioned' ? 'text-success' : ($account->provision_status === 'failed' ? 'text-danger' : 'text-muted') }}">
          {{ $account->provision_message }}
        </span>
      @else
        Isi data hosting account. Centang "Provision Otomatis" untuk langsung membuat akun di server via API.
      @endif
    </p>
  </div>

  <form method="POST" action="{{ $account->exists ? route('admin.hosting-account.update', $account) : route('admin.hosting-account.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Klien</label>
      <select name="client_id" class="form-select" style="{{ $selectStyle }}" required>
        <option value="">Pilih klien</option>
        @foreach ($clients as $client)
          <option value="{{ $client->id }}" @selected(old('client_id', $account->client_id) == $client->id)>{{ $client->name }}</option>
        @endforeach
      </select>
      @error('client_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Domain</label>
        <input type="text" name="domain" value="{{ old('domain', $account->domain) }}" placeholder="contoh.com" class="form-control form-control-sm" required>
        @error('domain') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Paket (nama plan di panel)</label>
        <input type="text" name="package" value="{{ old('package', $account->package) }}" placeholder="cloud_hosting_pro" class="form-control form-control-sm" required>
        @error('package') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Kalau provisioning otomatis: harus sama persis dengan nama package/plan yang sudah dibuat di WHM.</p>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Panel Hosting</label>
        <select name="panel" class="form-select" style="{{ $selectStyle }}">
          <option value="cpanel" @selected(old('panel', $account->panel) === 'cpanel')>cPanel / WHM</option>
          <option value="directadmin" @selected(old('panel', $account->panel) === 'directadmin')>DirectAdmin</option>
          <option value="plesk" @selected(old('panel', $account->panel) === 'plesk')>Plesk</option>
          <option value="vps" @selected(old('panel', $account->panel) === 'vps')>VPS / Manual (tanpa panel otomatis)</option>
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Server Terhubung (opsional)</label>
        <select name="server_id" class="form-select" style="{{ $selectStyle }}">
          <option value="">— Tanpa server (manual) —</option>
          @foreach ($servers as $srv)
            <option value="{{ $srv->id }}" @selected(old('server_id', $account->server_id) == $srv->id)>{{ $srv->name }} ({{ $srv->hostname }})</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Username Panel</label>
        <input type="text" name="username" value="{{ old('username', $account->username) }}" placeholder="contohus" class="form-control form-control-sm">
      </div>
    </div>

    <div class="rounded-3 border p-3 mb-3" style="{{ $account->exists && ! $account->product_id ? 'background:#fffbeb;border-color:#fde68a!important' : '' }}">
      <label class="form-label small fw-medium text-dark">Produk Terkait</label>
      <select name="product_id" class="form-select" style="{{ $selectStyle }};max-width:24rem">
        <option value="">— Tidak ditautkan ke produk manapun —</option>
        @foreach ($products as $p)
          <option value="{{ $p->id }}" @selected(old('product_id', $account->product_id) == $p->id)>
            {{ $p->category->name ?? '' }} — {{ $p->name }}
          </option>
        @endforeach
      </select>
      @error('product_id') <p class="text-danger mt-2 mb-0" style="font-size:12px">{{ $message }}</p> @enderror

      @if ($account->exists && ! $account->product_id)
        <p class="mt-2 mb-0" style="font-size:12px;color:#b45309">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Layanan ini belum tertaut ke produk manapun — biasanya karena dibuat sebelum fitur
          <b>Upgrade Paket Mandiri</b> ada. Klien <b>tidak akan melihat opsi upgrade</b> sampai ini diisi.
          Pilih produk yang paling sesuai dengan paket layanan ini saat ini.
        </p>
      @else
        <p class="text-muted mt-2 mb-0" style="font-size:11px">
          Dipakai untuk menentukan paket lain apa saja yang bisa jadi tujuan upgrade mandiri klien
          (harus kategori &amp; server yang sama, harga lebih tinggi).
        </p>
      @endif
    </div>

    <div class="rounded-3 border p-3 mb-3" style="{{ ! $account->server_id ? 'background:#fffbeb;border-color:#fde68a!important' : '' }}">
      <label class="form-label small fw-medium text-dark">Info Akses untuk Klien</label>
      <textarea name="client_details" rows="4" placeholder="Contoh untuk VPS:&#10;IP Address: 103.xx.xx.xx&#10;Username: root&#10;Password: ********&#10;Panel: https://xxx:4083"
                class="form-control form-control-sm" style="font-family:monospace;font-size:12px">{{ old('client_details', $account->client_details) }}</textarea>
      @error('client_details') <p class="text-danger mt-2 mb-0" style="font-size:12px">{{ $message }}</p> @enderror

      @if (! $account->server_id)
        <p class="mt-2 mb-0" style="font-size:12px;color:#b45309">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Layanan ini tidak terhubung server (mode manual — cocok untuk VPS, dedicated server, lisensi, dll).
          Klien <b>tidak punya cara lain</b> melihat info akses layanannya selain dari sini — isi setelah
          Anda setup layanan ini sendiri di luar sistem.
        </p>
      @else
        <p class="text-muted mt-2 mb-0" style="font-size:11px">
          Opsional untuk akun cPanel otomatis — dipakai kalau ada info tambahan
          yang perlu disampaikan ke klien di luar login SSO.
        </p>
      @endif
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        <i class="fa-solid fa-lock"></i> Disimpan terenkripsi di database, sama seperti kredensial server.
      </p>
    </div>

    @unless ($account->exists)
      <div class="rounded-3 p-3 mb-3" style="border:1px dashed #a5b4fc;background:#eef2ff">
        <label class="d-flex align-items-center gap-2 small fw-bold text-accent mb-2">
          <input type="checkbox" name="provision_now" value="1" id="provisionNow" @checked(old('provision_now')) class="form-check-input" style="margin-top:0">
          Provision Otomatis ke Server (buat akun cPanel sekarang via API)
        </label>
        <div>
          <label class="form-label small fw-medium text-dark">Password Akun cPanel</label>
          <div class="d-flex gap-2">
            <input type="password" name="provision_password" id="pwField" placeholder="Password untuk akun baru di server" class="form-control form-control-sm">
            <button type="button" onclick="lumoraGeneratePassword('pwField', null, 'pwChecklist')" class="btn btn-outline-secondary btn-sm text-nowrap flex-shrink-0">
              <i class="fa-solid fa-dice" style="font-size:11px"></i> Buatkan Otomatis
            </button>
          </div>
          <ul id="pwChecklist" class="text-muted mt-2 mb-0 ps-0" style="font-size:11px;list-style:none"></ul>
          <p class="text-muted mt-1 mb-0" style="font-size:11px">Hanya dipakai sekali untuk request ke API, tidak disimpan di database.</p>
        </div>
      </div>
    @endunless

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Harga</label>
        <input type="number" step="0.01" name="price" value="{{ old('price', $account->price) }}" class="form-control form-control-sm" required>
        @error('price') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Siklus Tagihan</label>
        <select name="billing_cycle" class="form-select" style="{{ $selectStyle }}">
          <option value="monthly" @selected(old('billing_cycle', $account->billing_cycle) === 'monthly')>Bulanan</option>
          <option value="quarterly" @selected(old('billing_cycle', $account->billing_cycle) === 'quarterly')>3 Bulan</option>
          <option value="semi_annually" @selected(old('billing_cycle', $account->billing_cycle) === 'semi_annually')>6 Bulan</option>
          <option value="annually" @selected(old('billing_cycle', $account->billing_cycle) === 'annually')>Tahunan</option>
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Jatuh Tempo Berikutnya</label>
        <input type="date" name="next_due_date" value="{{ old('next_due_date', optional($account->next_due_date)->format('Y-m-d')) }}" class="form-control form-control-sm">
      </div>
    </div>

    <div class="rounded-3 border p-3 mb-3" style="background:#f8fafc">
      <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
        <i class="fa-solid fa-clock"></i> Mode Tagihan
      </p>
      <div class="row g-2 mb-2">
        @foreach (['invoice' => 'Invoice Berkala (biasa)', 'deposit' => 'Potong Saldo per Jam'] as $modeKey => $modeLabel)
          @php $isActiveMode = old('billing_mode', $account->billing_mode ?? 'invoice') === $modeKey; @endphp
          <div class="col-6">
            <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100"
                   style="cursor:pointer;{{ $isActiveMode ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
              <input type="radio" name="billing_mode" value="{{ $modeKey }}" @checked($isActiveMode) class="d-none" data-billing-mode-radio>
              {{ $modeLabel }}
            </label>
          </div>
        @endforeach
      </div>

      <div id="hourlyRateField" class="{{ old('billing_mode', $account->billing_mode ?? 'invoice') === 'deposit' ? '' : 'd-none' }}">
        <label class="form-label small fw-medium text-dark">Tarif per Jam (Rp)</label>
        <input type="number" step="0.0001" name="hourly_rate" value="{{ old('hourly_rate', $account->hourly_rate) }}" class="form-control form-control-sm" style="max-width:14rem">
        @error('hourly_rate') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">
          Dipotong otomatis dari saldo klien tiap jam (lewat cron <code>lumora:charge-hourly-usage</code>).
          Kalau saldo habis, layanan otomatis di-suspend. Kolom "Harga" &amp; "Siklus Tagihan" di atas diabaikan untuk mode ini.
        </p>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Status</label>
      <select name="status" class="form-select" style="{{ $selectStyle }};max-width:16rem">
        <option value="pending" @selected(old('status', $account->status) === 'pending')>Pending</option>
        <option value="active" @selected(old('status', $account->status) === 'active')>Aktif</option>
        <option value="suspended" @selected(old('status', $account->status) === 'suspended')>Suspended</option>
        <option value="terminated" @selected(old('status', $account->status) === 'terminated')>Terminated</option>
      </select>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Untuk suspend/unsuspend/terminate lewat API, gunakan tombol aksi di halaman daftar, bukan dropdown ini.</p>
    </div>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.hosting-accounts') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

  <script>
    function lumoraPasswordChecks(pw) {
      return [
        { label: 'Minimal 8 karakter', ok: pw.length >= 8 },
        { label: 'Huruf besar & kecil', ok: /[a-z]/.test(pw) && /[A-Z]/.test(pw) },
        { label: 'Mengandung angka', ok: /[0-9]/.test(pw) },
        { label: 'Mengandung simbol (!@#$dst)', ok: /[^a-zA-Z0-9]/.test(pw) },
      ];
    }

    function lumoraRenderChecklist(pw, checklistId) {
      const el = document.getElementById(checklistId);
      if (!el) return;
      el.innerHTML = lumoraPasswordChecks(pw).map(c =>
        `<li class="${c.ok ? 'text-success' : 'text-muted'}" style="margin-bottom:.25rem"><i class="fa-solid ${c.ok ? 'fa-circle-check' : 'fa-circle'}" style="font-size:9px"></i> ${c.label}</li>`
      ).join('');
    }

    function lumoraGeneratePassword(pwFieldId, confirmFieldId, checklistId) {
      const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
      const lower = 'abcdefghijkmnpqrstuvwxyz';
      const digits = '23456789';
      const symbols = '!@#$%&*';
      const all = upper + lower + digits + symbols;

      const pick = (set) => set[Math.floor(Math.random() * set.length)];

      let pw = [pick(upper), pick(lower), pick(digits), pick(symbols)];
      for (let i = 0; i < 8; i++) pw.push(pick(all));
      pw = pw.sort(() => Math.random() - 0.5).join('');

      const pwField = document.getElementById(pwFieldId);
      pwField.value = pw;
      pwField.type = 'text';

      if (confirmFieldId) {
        const confirmField = document.getElementById(confirmFieldId);
        if (confirmField) confirmField.value = pw;
      }

      lumoraRenderChecklist(pw, checklistId);
    }

    document.addEventListener('DOMContentLoaded', () => {
      const pwField = document.getElementById('pwField');
      if (pwField) {
        pwField.addEventListener('input', () => lumoraRenderChecklist(pwField.value, 'pwChecklist'));
      }
    });
  </script>

  <script>
    // Tampilkan kolom Tarif per Jam hanya saat mode "Potong Saldo per Jam" dipilih.
    (function () {
      const radios = document.querySelectorAll('[data-billing-mode-radio]');
      const field = document.getElementById('hourlyRateField');

      function sync() {
        const active = document.querySelector('[data-billing-mode-radio]:checked')?.value;
        field.classList.toggle('d-none', active !== 'deposit');

        radios.forEach(function (r) {
          const label = r.closest('label');
          if (r.checked) {
            label.style.borderColor = '#4f46e5';
            label.style.background = 'rgba(79,70,229,.06)';
            label.style.color = '#4338ca';
          } else {
            label.style.borderColor = '';
            label.style.background = '';
            label.style.color = '';
          }
        });
      }

      radios.forEach(r => r.addEventListener('change', sync));
      sync();
    })();
  </script>

@endsection
