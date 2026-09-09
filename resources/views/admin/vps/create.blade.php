@extends('layouts.admin')

@section('title', 'Tambah VPS')

@section('content')

  <a href="{{ route('admin.vps') }}" class="text-decoration-none text-muted" style="font-size:12px">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Layanan VPS
  </a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-1">Tambah VPS</h1>
  <p class="small text-muted mb-4">
    Buat VM baru langsung di provider cloud. Berbeda dari Hosting Account biasa —
    di sini yang dibuat adalah mesin virtual utuh, bukan akun di server yang sudah ada.
  </p>

  @if ($servers->isEmpty())
    <div class="card border rounded-4 p-5 text-center" style="max-width:42rem">
      <i class="fa-solid fa-cloud text-muted mb-3" style="font-size:1.75rem"></i>
      <p class="fw-medium text-dark mb-1">Belum ada server cloud terhubung</p>
      <p class="text-muted mb-3" style="font-size:14px">
        Tambahkan dulu server bertipe IDCloudHost, lengkap dengan API Token dan kartu harganya.
      </p>
      <a href="{{ route('admin.servers.create') }}" class="btn btn-primary btn-sm mx-auto" style="width:fit-content">Tambah Server Cloud</a>
    </div>
  @else
    <form method="POST" action="{{ route('admin.vps.store') }}" style="max-width:52rem">
      @csrf

      <div class="row g-3">
        <div class="col-12 col-lg-8">
          <div class="card border rounded-4 p-4 mb-3">
            <h2 class="small fw-bold text-dark mb-3">Pemilik &amp; Server</h2>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">Klien</label>
                <select name="client_id" class="form-select" required>
                  <option value="">— Pilih klien —</option>
                  @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                      {{ $client->name }} (saldo Rp {{ number_format((float) $client->balance, 0, ',', '.') }})
                    </option>
                  @endforeach
                </select>
                @error('client_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">Server Cloud</label>
                <select name="server_id" id="serverSelect" class="form-select" required>
                  @foreach ($servers as $srv)
                    @php $r = \App\Services\Billing\HourlyRateCalculator::effectiveRates($srv); @endphp
                    <option value="{{ $srv->id }}" @selected(old('server_id') == $srv->id)
                            data-rates="{{ json_encode([
                              'vcpu' => $r['vcpu'], 'ram' => $r['ram'], 'disk' => $r['storage'],
                              'backup' => $r['backup'], 'windows' => $r['windows'],
                            ]) }}">
                      {{ $srv->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-medium text-dark">Nama VM</label>
                <input type="text" name="domain" value="{{ old('domain') }}" class="form-control" placeholder="vps-klien-01" required>
                <p class="text-muted mt-1 mb-0" style="font-size:11px">
                  Huruf, angka, dan strip saja. Ini label VM di provider — bukan domain sungguhan.
                </p>
                @error('domain') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              </div>
            </div>
          </div>

          <div class="card border rounded-4 p-4 mb-3">
            <h2 class="small fw-bold text-dark mb-3">Spesifikasi</h2>

            @if ($products->isNotEmpty())
              <div class="mb-3">
                <label class="form-label small fw-medium text-dark">Pakai Paket yang Sudah Ada <span class="text-muted fw-normal">(opsional)</span></label>
                <select id="productPreset" class="form-select">
                  <option value="">— Isi manual di bawah —</option>
                  @foreach ($products as $product)
                    @php $ps = json_decode((string) $product->panel_package, true); @endphp
                    @if (is_array($ps) && isset($ps['vcpu']))
                      <option value="{{ $product->id }}" data-spec="{{ json_encode($ps) }}">
                        {{ $product->name }} — {{ $ps['vcpu'] }} vCPU / {{ $ps['ram'] }} MB / {{ $ps['disk'] }} GB
                      </option>
                    @endif
                  @endforeach
                </select>
                <p class="text-muted mt-1 mb-0" style="font-size:11px">Memilih paket akan mengisi kolom di bawah otomatis.</p>
              </div>
            @endif

            <div class="row g-3">
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">vCPU</label>
                <input type="number" name="vcpu" id="fVcpu" min="{{ $limits['vcpu']['min'] }}" max="{{ $limits['vcpu']['max'] }}" value="{{ old('vcpu', $limits['vcpu']['min']) }}" class="form-control" required>
                <p class="text-muted mt-1 mb-0" style="font-size:10px">Provider: {{ $limits['vcpu']['min'] }}–{{ $limits['vcpu']['max'] }}</p>
              </div>
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">RAM (MB)</label>
                <input type="number" name="ram" id="fRam" min="{{ $limits['ram']['min'] }}" max="{{ $limits['ram']['max'] }}" step="512" value="{{ old('ram', max(1024, $limits['ram']['min'])) }}" class="form-control" required>
                <p class="text-muted mt-1 mb-0" style="font-size:10px">Provider: {{ $limits['ram']['min'] }}–{{ number_format($limits['ram']['max']) }} MB</p>
              </div>
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">Disk (GB)</label>
                <input type="number" name="disk" id="fDisk" min="{{ $limits['disks']['min'] }}" max="{{ $limits['disks']['max'] }}" value="{{ old('disk', max(20, $limits['disks']['min'])) }}" class="form-control" required>
                <p class="text-muted mt-1 mb-0" style="font-size:10px">Provider: {{ $limits['disks']['min'] }}–{{ $limits['disks']['max'] }} GB</p>
              </div>
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">Backup</label>
                <select name="backup_enabled" id="fBackup" class="form-select">
                  <option value="0">Tidak</option>
                  <option value="1" @selected(old('backup_enabled'))>Ya</option>
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">OS / Aplikasi</label>
                <select name="os_name" id="fOs" class="form-select" required>
                  <option value="">— Pilih OS atau Aplikasi —</option>
                  @php
                    $plain = collect($osImages)->where('is_app_catalog', false);
                    $apps = collect($osImages)->where('is_app_catalog', true);
                  @endphp
                  @if ($plain->isNotEmpty())
                    <optgroup label="Sistem Operasi">
                      @foreach ($plain as $img)
                        <option value="{{ $img['os_name'] ?? '' }}"
                                data-versions="{{ json_encode(collect($img['versions'] ?? [])->pluck('os_version')) }}"
                                @selected(old('os_name') === ($img['os_name'] ?? ''))>{{ $img['display_name'] ?? $img['os_name'] }}</option>
                      @endforeach
                    </optgroup>
                  @endif
                  @if ($apps->isNotEmpty())
                    <optgroup label="Aplikasi Siap Pakai">
                      @foreach ($apps as $img)
                        <option value="{{ $img['os_name'] ?? '' }}"
                                data-versions="{{ json_encode(collect($img['versions'] ?? [])->pluck('os_version')) }}"
                                @selected(old('os_name') === ($img['os_name'] ?? ''))>{{ $img['display_name'] ?? $img['os_name'] }}</option>
                      @endforeach
                    </optgroup>
                  @endif
                </select>
                @if ($apiError)
                  <p class="mt-1 mb-0" style="font-size:11px;color:#b45309">
                    <i class="fa-solid fa-triangle-exclamation"></i> Daftar OS gagal diambil: {{ $apiError }}
                  </p>
                @endif
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">Versi</label>
                <select name="os_version" id="fOsVer" class="form-select" required>
                  <option value="">— Pilih OS dulu —</option>
                </select>
              </div>

              @if ($locations)
                <div class="col-sm-6">
                  <label class="form-label small fw-medium text-dark">Lokasi Datacenter</label>
                  <select name="location" class="form-select">
                    @foreach ($locations as $loc)
                      <option value="{{ $loc['slug'] ?? '' }}" @selected(($loc['is_default'] ?? false) || old('location') === ($loc['slug'] ?? ''))>
                        {{ $loc['display_name'] ?? $loc['slug'] }}{{ ! empty($loc['is_default']) ? ' (default)' : '' }}
                      </option>
                    @endforeach
                  </select>
                  <p class="text-muted mt-1 mb-0" style="font-size:11px">Mengikuti lokasi server kalau dikosongkan.</p>
                </div>
              @endif

              @if ($pools)
                <div class="col-sm-6">
                  <label class="form-label small fw-medium text-dark">Kelas Server</label>
                  <select name="pool_uuid" class="form-select">
                    <option value="">— Default —</option>
                    @foreach ($pools as $pool)
                      <option value="{{ $pool['uuid'] ?? '' }}" @selected(old('pool_uuid') === ($pool['uuid'] ?? ''))>
                        {{ $pool['name'] ?? '?' }}{{ ! empty($pool['is_default_designated']) ? ' (default)' : '' }}
                      </option>
                    @endforeach
                  </select>
                  <p class="text-muted mt-1 mb-0" style="font-size:11px">{{ collect($pools)->pluck('description')->filter()->implode(' · ') }}</p>
                </div>
              @endif
            </div>
            <p class="text-muted mt-2 mb-0" style="font-size:11px">
              <i class="fa-solid fa-circle-info"></i>
              Daftar OS, lokasi, dan kelas server ditarik langsung dari provider — tidak perlu mengetik manual.
            </p>
          </div>

          <div class="card border rounded-4 p-4 mb-3">
            <h2 class="small fw-bold text-dark mb-3">Login ke Dalam VM</h2>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">Username</label>
                <input type="text" name="username" value="{{ old('username', 'ubuntu') }}" class="form-control" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">Password</label>
                <div class="d-flex gap-2">
                  <input type="text" name="password" id="fPass" value="{{ old('password') }}" class="form-control" required minlength="8">
                  <button type="button" onclick="genPass()" class="btn btn-outline-secondary text-nowrap flex-shrink-0">
                    <i class="fa-solid fa-dice" style="font-size:11px"></i> Buatkan
                  </button>
                </div>
                <p class="text-muted mt-1 mb-0" style="font-size:11px">Min. 8 karakter, harus ada huruf besar, kecil, dan angka.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card border rounded-4 p-4 mb-3" style="position:sticky;top:5rem">
            <h2 class="small fw-bold text-dark mb-3">Tagihan</h2>

            <label class="form-label small fw-medium text-dark">Mode</label>
            <select name="billing_mode" id="fMode" class="form-select mb-3">
              <option value="deposit" @selected(old('billing_mode', 'deposit') === 'deposit')>Potong Saldo per Jam</option>
              <option value="invoice" @selected(old('billing_mode') === 'invoice')>Invoice Berkala</option>
            </select>

            <div id="depositInfo">
              <div class="rounded-3 p-3 mb-2" style="background:rgba(79,70,229,.06);border:1px solid #c7d2fe">
                <p class="text-muted mb-0" style="font-size:11px">Estimasi Tarif Jual</p>
                <p class="fw-bold text-dark mb-0" id="estHour" style="font-size:1.25rem">—</p>
                <p class="text-muted mb-0" id="estMonth" style="font-size:11px">—</p>
              </div>
              <p class="text-muted mb-0" style="font-size:11px">
                Dihitung dari kartu harga server × spesifikasi. Saldo klien dipotong otomatis tiap jam,
                VM di-suspend kalau saldo habis.
              </p>
            </div>

            <div id="invoiceInfo" class="d-none">
              <label class="form-label small fw-medium text-dark">Harga per Siklus</label>
              <input type="number" step="0.01" name="price" value="{{ old('price', 0) }}" class="form-control mb-2">
              <label class="form-label small fw-medium text-dark">Siklus</label>
              <select name="billing_cycle" class="form-select">
                <option value="monthly">Bulanan</option>
                <option value="quarterly">3 Bulan</option>
                <option value="semi_annually">6 Bulan</option>
                <option value="annually">Tahunan</option>
              </select>
            </div>

            <label class="d-flex align-items-start gap-2 small text-dark mt-3 pt-3 border-top mb-0">
              <input type="checkbox" name="provision_now" value="1" checked class="form-check-input flex-shrink-0" style="margin-top:2px">
              <span>
                <b>Buat VM sekarang</b>
                <span class="d-block text-muted" style="font-size:11px">Hilangkan centang kalau cuma mau mencatat VM yang sudah dibuat manual.</span>
              </span>
            </label>

            <button type="submit" class="btn btn-primary w-100 mt-3">
              <i class="fa-solid fa-cloud-arrow-up" style="font-size:11px"></i> Buat VPS
            </button>
          </div>
        </div>
      </div>
    </form>

    <script>
      // Estimasi tarif langsung berubah saat spek diubah -- supaya admin
      // tahu berapa yang akan ditagihkan SEBELUM VM dibuat.
      // Versi OS mengikuti OS yang dipilih -- daftarnya dari API, jadi
      // tidak mungkin memilih kombinasi yang tidak ada di provider.
      const osSelect = document.getElementById('fOs');
      const verSelect = document.getElementById('fOsVer');

      function isiVersi() {
        const raw = osSelect.selectedOptions[0]?.dataset.versions;
        const versions = raw ? JSON.parse(raw) : [];
        const before = verSelect.value;

        verSelect.innerHTML = versions.length
          ? ''
          : '<option value="">— Tidak ada versi —</option>';

        versions.forEach(function (v) {
          const opt = document.createElement('option');
          opt.value = v;
          opt.textContent = v;
          if (v === before || v === @json(old('os_version'))) opt.selected = true;
          verSelect.appendChild(opt);
        });

        hitungEstimasi();
      }

      osSelect.addEventListener('change', isiVersi);
      isiVersi();

      function hitungEstimasi() {
        const opt = document.getElementById('serverSelect').selectedOptions[0];
        if (! opt) return;

        const r = JSON.parse(opt.dataset.rates || '{}');
        const vcpu = parseFloat(document.getElementById('fVcpu').value) || 0;
        const ramGb = (parseFloat(document.getElementById('fRam').value) || 0) / 1024;
        const disk = parseFloat(document.getElementById('fDisk').value) || 0;
        const backup = document.getElementById('fBackup').value === '1';
        const isWin = document.getElementById('fOs').value.toLowerCase().includes('windows');

        let perJam = vcpu * (r.vcpu || 0) + ramGb * (r.ram || 0) + disk * (r.disk || 0);
        if (backup) perJam += disk * (r.backup || 0);
        if (isWin) perJam += vcpu * (r.windows || 0);

        const fmt = (n) => 'Rp ' + n.toLocaleString('id-ID', { maximumFractionDigits: 2 });

        document.getElementById('estHour').textContent = perJam > 0 ? fmt(perJam) + ' / jam' : 'Kartu harga belum diisi';
        document.getElementById('estMonth').textContent = perJam > 0 ? '± ' + fmt(perJam * 730) + ' / bulan (730 jam)' : '';
      }

      ['fVcpu', 'fRam', 'fDisk', 'fBackup', 'fOs', 'serverSelect'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', hitungEstimasi);
        document.getElementById(id).addEventListener('change', hitungEstimasi);
      });

      document.getElementById('fMode').addEventListener('change', function () {
        const deposit = this.value === 'deposit';
        document.getElementById('depositInfo').classList.toggle('d-none', ! deposit);
        document.getElementById('invoiceInfo').classList.toggle('d-none', deposit);
      });

      document.getElementById('productPreset')?.addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        if (! opt || ! opt.dataset.spec) return;

        const s = JSON.parse(opt.dataset.spec);
        document.getElementById('fVcpu').value = s.vcpu ?? 1;
        document.getElementById('fRam').value = s.ram ?? 1024;
        document.getElementById('fDisk').value = s.disk ?? 20;
        document.getElementById('fOs').value = s.os_name ?? 'ubuntu';
        document.getElementById('fOsVer').value = s.os_version ?? '';
        document.getElementById('fBackup').value = s.backup_enabled ? '1' : '0';
        hitungEstimasi();
      });

      function genPass() {
        const U = 'ABCDEFGHJKLMNPQRSTUVWXYZ', L = 'abcdefghijkmnpqrstuvwxyz', D = '23456789';
        const all = U + L + D;
        const pick = (s) => s[Math.floor(Math.random() * s.length)];
        let p = [pick(U), pick(L), pick(D)];
        for (let i = 0; i < 9; i++) p.push(pick(all));
        document.getElementById('fPass').value = p.sort(() => Math.random() - 0.5).join('');
      }

      hitungEstimasi();
    </script>
  @endif

@endsection
