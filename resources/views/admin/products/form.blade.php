@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Produk' : 'Tambah Produk')

@section('content')
  @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

  <div class="mb-4 d-flex align-items-start justify-content-between gap-3 flex-wrap">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">{{ $product->exists ? 'Edit Produk' : 'Tambah Produk' }}</h1>
      @if ($product->exists && $product->category)
        <p class="small text-muted mb-0">
          URL: <a href="{{ $product->category->productUrl($product) }}" target="_blank" class="text-accent">{{ $product->category->productUrl($product) }}</a>
        </p>
      @endif
    </div>
    @if ($product->exists)
      <a href="{{ route('admin.products.options.index', $product) }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">
        <i class="fa-solid fa-sliders"></i> Kelola Opsi Konfigurasi
      </a>
    @endif
  </div>

  @if ($categories->isEmpty())
    <div class="card border rounded-4 p-4 text-center text-muted small" style="max-width:42rem">
      Belum ada kategori produk.
      <a href="{{ route('admin.product-categories.create') }}" class="text-accent">Buat kategori dulu</a>.
    </div>
  @else
    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="row g-3" style="max-width:70rem">
      @csrf
      @if ($product->exists) @method('PUT') @endif

      <div class="col-12 col-lg-8">
        <div class="card border rounded-4 p-4 mb-3">
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Kategori</label>
              <select name="product_category_id" id="categorySelect" class="form-select" style="{{ $selectStyle }}" required>
                <option value="">Pilih kategori</option>
                @foreach ($categories as $cat)
                  <option value="{{ $cat->id }}" data-type="{{ $cat->type ?? 'hosting' }}" @selected(old('product_category_id', $product->product_category_id) == $cat->id)>
                    {{ $cat->name }} — {{ ($cat->type ?? 'hosting') === 'vps' ? 'VPS' : 'Hosting' }}
                  </option>
                @endforeach
              </select>
              @error('product_category_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Nama Produk</label>
              <input type="text" name="name" id="nameInput" value="{{ old('name', $product->name) }}" class="form-control form-control-sm" required placeholder="Cloud Hosting - Pro">
              @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">
              Slug URL
              <span id="slugAutoBadge" class="text-muted fw-normal" style="font-size:10.5px">(terisi otomatis saat mengetik Nama Produk)</span>
            </label>
            <input type="text" name="slug" id="slugInput" value="{{ old('slug', $product->slug) }}" class="form-control form-control-sm" placeholder="otomatis dari nama">
            @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>

          <script>
            (function () {
              const name = document.getElementById('nameInput');
              const slug = document.getElementById('slugInput');
              const badge = document.getElementById('slugAutoBadge');

              const slugify = (s) => s.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');

              // slugTouched jadi true begitu klien MENGETIK sendiri di kolom
              // slug -- sejak itu auto-fill berhenti supaya tidak menimpa
              // slug yang sudah sengaja diedit manual.
              let slugTouched = slug.value.length > 0;

              function markTouched() {
                slugTouched = true;
                badge.classList.add('d-none');
              }

              function autofill() {
                if (! slugTouched) slug.value = slugify(name.value);
              }

              // 'input' menangkap ketik biasa; 'paste' + 'change' jadi jaring
              // pengaman untuk kasus isi lewat paste atau autofill browser
              // yang kadang tidak memicu event 'input' di semua browser.
              slug.addEventListener('input', markTouched);
              name.addEventListener('input', autofill);
              name.addEventListener('change', autofill);
              name.addEventListener('paste', () => setTimeout(autofill, 0));

              // Kalau field Nama sudah terisi duluan (mis. balik dari halaman
              // lain, atau autofill browser sebelum listener terpasang),
              // langsung sinkron sekali di awal -- bukan menunggu klien
              // mengetik ulang supaya slug baru muncul.
              autofill();
            })();
          </script>

          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Tagline <span class="text-muted fw-normal">(1 baris, tampil di kartu produk)</span></label>
            <input type="text" name="tagline" maxlength="255" value="{{ old('tagline', $product->tagline) }}" class="form-control form-control-sm" placeholder="Cocok untuk website bisnis & toko online">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Deskripsi</label>
            <textarea name="description" rows="5" class="form-control form-control-sm">{{ old('description', $product->description) }}</textarea>
          </div>

          <div>
            <label class="form-label small fw-medium text-dark">Daftar Fitur <span class="text-muted fw-normal">(satu per baris)</span></label>
            <textarea name="features_raw" rows="6" class="form-control form-control-sm" style="font-family:monospace;font-size:12px" placeholder="10 GB SSD Storage&#10;Unlimited Bandwidth&#10;Free SSL&#10;1 Domain">{{ old('features_raw', $product->features ? implode("\n", $product->features) : '') }}</textarea>
          </div>
        </div>

        <div class="card border rounded-4 p-4 mb-3" id="pricingCyclesCard">
          <h2 class="small fw-bold text-dark mb-1">Harga per Siklus Tagihan</h2>
          <p class="text-muted mb-3" style="font-size:12px">Kosongkan siklus yang tidak dijual untuk produk ini. Minimal isi satu.</p>
          @error('price_monthly') <p class="text-danger mb-2" style="font-size:12px">{{ $message }}</p> @enderror

          <div class="row g-3 mb-3">
            @foreach (\App\Models\Product::CYCLES as $key => $label)
              <div class="col-sm-6">
                <label class="form-label small fw-medium text-dark">{{ $label }}</label>
                <input type="number" step="0.01" name="price_{{ $key }}" value="{{ old('price_' . $key, $product->{'price_' . $key}) }}" class="form-control form-control-sm" placeholder="Kosongkan jika tidak dijual">
              </div>
            @endforeach
          </div>

          @if (auth('admin')->user()->isSuperadmin())
            <div class="mb-3" style="max-width:16rem">
              <label class="form-label small fw-medium text-dark">Jumlah Hari untuk Siklus "Custom" <span class="text-warning fw-normal" style="font-size:11px"><i class="fa-solid fa-lock"></i> Superadmin</span></label>
              <input type="number" min="1" name="custom_cycle_days" value="{{ old('custom_cycle_days', $product->custom_cycle_days) }}" class="form-control form-control-sm" placeholder="Contoh: 45">
              @error('custom_cycle_days') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              <p class="text-muted mt-1 mb-0" style="font-size:11px">Cuma dipakai kalau kolom "Custom" di atas diisi harga.</p>
            </div>
          @endif

          <div>
            <label class="form-label small fw-medium text-dark">Biaya Setup <span class="text-muted fw-normal">(sekali bayar, opsional)</span></label>
            <input type="number" step="0.01" name="setup_fee" value="{{ old('setup_fee', $product->setup_fee ?? 0) }}" class="form-control form-control-sm">
          </div>
        </div>

        <div class="card border rounded-4 p-4">
          <h2 class="small fw-bold text-dark mb-1">Provisioning Otomatis</h2>
          <p class="text-muted mb-3" style="font-size:12px">
            Data ini menentukan ke server mana dan dengan paket apa akun cPanel dibuat otomatis
            saat order produk ini lunas. Boleh dikosongkan kalau provisioning-nya manual.
          </p>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Server Tujuan</label>
              <select name="server_id" id="serverSelect" class="form-select" style="{{ $selectStyle }}" data-server-edit-base="{{ url('/admin/servers') }}/__ID__/edit">
                <option value="">— Manual, tanpa auto-provisioning —</option>
                @foreach ($servers as $srv)
                  <option value="{{ $srv->id }}" data-kind="{{ $srv->isCloud() ? 'vps' : 'hosting' }}"
                          @selected(old('server_id', $product->server_id) == $srv->id)>
                    {{ $srv->name }}{{ $srv->isCloud() ? ' (Cloud/VPS · ' . $srv->vpsLabel() . ')' : ' (cPanel)' }}
                  </option>
                @endforeach
              </select>
              <p class="text-muted mt-1 mb-0" style="font-size:11px">Hanya server yang cocok dengan jenis kategori yang ditampilkan.</p>
            </div>
            <div class="col-sm-6" id="cpanelPackageField">
              <label class="form-label small fw-medium text-dark">Nama Package di WHM/cPanel</label>
              <input type="text" name="panel_package" id="panelPackageInput" value="{{ old('panel_package', $product->panel_package) }}" class="form-control form-control-sm" placeholder="cloud_hosting_pro">
              <p class="text-muted mt-1 mb-0" style="font-size:11px">Harus sama persis dengan nama plan yang sudah dibuat di WHM.</p>
            </div>
          </div>

          {{-- Spesifikasi VPS. OS SENGAJA TIDAK ADA di sini -- klien yang
               memilih OS/aplikasi saat memesan, jadi satu paket VPS bisa
               dipakai untuk OS apa pun. --}}
          @php
            $vmSpec = json_decode((string) $product->panel_package, true);
            $vmSpec = is_array($vmSpec) && isset($vmSpec['vcpu']) ? $vmSpec : [];
          @endphp
          <div id="vpsSpecFields" class="d-none mt-3 pt-3 border-top">
            <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
              <i class="fa-solid fa-microchip"></i> Spesifikasi VPS
            </p>
            {{-- Isian khusus provider (config/vps_providers.php › product_fields).
                 Yang tampil hanya milik provider server yang dipilih; yang
                 disembunyikan di-disable supaya tidak terkirim. --}}
            @foreach (config('vps_providers', []) as $driver => $providerCfg)
              @if (! empty($providerCfg['product_fields']))
                <div class="row g-3 mb-3 d-none" data-provider-fields="{{ $driver }}">
                  @foreach ($providerCfg['product_fields'] as $fKey => $field)
                    <div class="col-sm-6 col-lg-4">
                      <label class="form-label small fw-medium text-dark">{{ $field['label'] }} <span class="text-muted fw-normal">({{ $providerCfg['label'] }})</span></label>
                      <input type="text" name="vm_{{ $fKey }}" value="{{ old('vm_' . $fKey, $vmSpec[$fKey] ?? ($field['default'] ?? '')) }}"
                             placeholder="{{ $field['placeholder'] ?? '' }}" class="form-control form-control-sm" data-source="{{ $field['source'] ?? '' }}" autocomplete="off" disabled>
                      @error('vm_' . $fKey) <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
                    </div>
                  @endforeach
                </div>
              @endif
            @endforeach
            <datalist id="dlSizes"></datalist>
            <datalist id="dlRegions"></datalist>

            <div id="componentSpecFields">
              <p id="sizeModelNote" class="text-muted mb-2 d-none" style="font-size:11px">
                <i class="fa-solid fa-circle-info"></i> Spesifikasi (vCPU, RAM, disk) mengikuti <b>size</b> yang dipilih di atas — tidak perlu diisi manual.
              </p>
            <div class="row g-3">
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">vCPU (Core)</label>
                <input type="number" name="vm_vcpu" id="vmVcpu" min="1" max="16" value="{{ old('vm_vcpu', $vmSpec['vcpu'] ?? 1) }}" class="form-control form-control-sm">
              </div>
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">RAM (MB)</label>
                <input type="number" name="vm_ram" id="vmRam" min="512" step="512" value="{{ old('vm_ram', $vmSpec['ram'] ?? 1024) }}" class="form-control form-control-sm">
                <p class="text-muted mt-1 mb-0" style="font-size:10px">1024 = 1 GB</p>
              </div>
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">Disk (GB)</label>
                <input type="number" name="vm_disk" id="vmDisk" min="20" value="{{ old('vm_disk', $vmSpec['disk'] ?? 20) }}" class="form-control form-control-sm">
              </div>
              <div class="col-6 col-lg-3">
                <label class="form-label small fw-medium text-dark">Backup Otomatis</label>
                <select name="vm_backup" id="vmBackup" class="form-select form-select-sm">
                  <option value="0" @selected(! ($vmSpec['backup_enabled'] ?? false))>Tidak</option>
                  <option value="1" @selected($vmSpec['backup_enabled'] ?? false)>Ya (biaya tambahan)</option>
                </select>
              </div>
            </div>
            </div>

            {{-- Harga modal provider untuk spek di atas (diperbarui otomatis). --}}
            <div id="vpsCostBox" class="rounded-3 border px-3 py-2 mt-3 small d-none" style="background:#f8fafc"></div>

            <div class="mt-3 pt-3 border-top">
              <label class="form-label small fw-medium text-dark">Cara Menagih</label>
              <select name="billing_mode" id="vmBillingMode" class="form-select form-select-sm" style="max-width:22rem">
                <option value="invoice" @selected(old('billing_mode', $product->billing_mode ?? 'invoice') === 'invoice')>Invoice Berkala (bulanan, dst)</option>
                <option value="deposit" @selected(old('billing_mode', $product->billing_mode) === 'deposit')>Potong Saldo per Jam</option>
              </select>
              <p class="text-muted mt-1 mb-0" style="font-size:11px">
                <b>Invoice berkala</b>: ditagih seperti hosting biasa, pakai harga &amp; siklus di kartu "Harga per Siklus Tagihan" di atas.
                <br><b>Saldo per jam</b>: klien topup dulu, dipotong otomatis tiap jam sesuai pemakaian — kartu harga siklus disembunyikan, diganti kartu harga per-jam di bawah.
              </p>
            </div>

            {{-- Kartu harga per-jam produk ini -- muncul hanya waktu "Potong
                 Saldo per Jam" dipilih. Kalau dikosongkan semua (Mode Isi
                 Manual, semua field kosong) & tidak pilih markup, sistem
                 jatuh ke kartu harga milik SERVER tujuan sebagai cadangan
                 (lihat HourlyRateCalculator::effectiveRates()) -- jadi
                 produk lama yang belum sempat diisi di sini tetap jalan. --}}
            <div id="hourlyPricingCard" class="d-none mt-3 pt-3 border-top">
              <p class="fw-bold text-muted mb-1" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
                <i class="fa-solid fa-tags"></i> Kartu Harga (per jam) — khusus produk ini
              </p>
              <p class="text-muted mb-3" style="font-size:11px">
                Kosongkan semuanya kalau mau ikut kartu harga server tujuan (cadangan lama). Isi di sini kalau produk
                ini perlu harga jual sendiri, beda dari produk VPS lain yang kebetulan satu server.
              </p>

              @error('pricing_mode') <p class="text-danger mb-2" style="font-size:12px">{{ $message }}</p> @enderror

              <div class="row g-2 mb-3">
                @foreach (['manual' => 'Isi Manual', 'markup' => 'Markup % dari Harga Modal Server'] as $mKey => $mLabel)
                  @php $activeMode = old('pricing_mode', $product->pricing_mode ?? 'manual') === $mKey; @endphp
                  <div class="col-6">
                    <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100"
                           style="cursor:pointer;{{ $activeMode ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
                      <input type="radio" name="pricing_mode" value="{{ $mKey }}" @checked($activeMode) class="d-none" data-product-pricing-mode>
                      {{ $mLabel }}
                    </label>
                  </div>
                @endforeach
              </div>

              <div id="productMarkupFields" class="{{ old('pricing_mode', $product->pricing_mode ?? 'manual') === 'markup' ? '' : 'd-none' }} rounded-3 border p-3 mb-3" style="background:#f8fafc">
                <div class="row g-3 align-items-end">
                  <div class="col-sm-5">
                    <label class="form-label small fw-medium text-dark">Markup (%)</label>
                    <input type="number" step="0.01" min="0" name="markup_percent" value="{{ old('markup_percent', $product->markup_percent ?? 50) }}" class="form-control form-control-sm">
                    <p class="text-muted mt-1 mb-0" style="font-size:10px">Mis. 50 = jual 1,5× harga modal server tujuan.</p>
                  </div>
                  <div class="col-sm-7">
                    @if ($product->server_id && $product->server?->cost_cached_at)
                      <p class="text-muted mb-1" style="font-size:11px">
                        Harga modal server tujuan (tersimpan {{ $product->server->cost_cached_at->diffForHumans() }}):
                        vCPU {{ number_format((float) ($product->server->cost_cache['vcpu'] ?? 0), 3) }} ·
                        RAM {{ number_format((float) ($product->server->cost_cache['ram'] ?? 0), 3) }} ·
                        Disk {{ number_format((float) ($product->server->cost_cache['storage'] ?? 0), 3) }}
                      </p>
                      <a href="{{ route('admin.servers.edit', $product->server_id) }}" target="_blank" class="text-decoration-underline" style="font-size:11px">Refresh harga modal di halaman Server →</a>
                    @elseif ($product->server_id)
                      <p class="mb-0" style="font-size:11px;color:#b45309">
                        <i class="fa-solid fa-triangle-exclamation"></i> Server tujuan belum pernah ditarik harga modalnya —
                        <a href="{{ route('admin.servers.edit', $product->server_id) }}" target="_blank" style="color:inherit" class="text-decoration-underline">tarik dulu di halaman Server</a>, baru mode markup bisa menghitung.
                      </p>
                    @else
                      <p class="text-muted mb-0" style="font-size:11px">Pilih &amp; simpan "Server Tujuan" dulu, baru harga modalnya bisa dibaca di sini.</p>
                    @endif
                  </div>
                </div>
              </div>

              <div id="productManualRateFields" class="{{ old('pricing_mode', $product->pricing_mode ?? 'manual') === 'markup' ? 'd-none' : '' }}">
                <div class="row g-3">
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-medium text-dark">Harga per vCPU</label>
                    <input type="number" step="0.000001" min="0" name="price_per_vcpu_hour" value="{{ old('price_per_vcpu_hour', $product->price_per_vcpu_hour) }}" class="form-control form-control-sm">
                  </div>
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-medium text-dark">Harga per GB RAM</label>
                    <input type="number" step="0.000001" min="0" name="price_per_ram_gb_hour" value="{{ old('price_per_ram_gb_hour', $product->price_per_ram_gb_hour) }}" class="form-control form-control-sm">
                  </div>
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-medium text-dark">Harga per GB Storage</label>
                    <input type="number" step="0.000001" min="0" name="price_per_storage_gb_hour" value="{{ old('price_per_storage_gb_hour', $product->price_per_storage_gb_hour) }}" class="form-control form-control-sm">
                  </div>
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-medium text-dark">Harga per GB Backup</label>
                    <input type="number" step="0.000001" min="0" name="price_per_backup_gb_hour" value="{{ old('price_per_backup_gb_hour', $product->price_per_backup_gb_hour) }}" class="form-control form-control-sm">
                  </div>
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-medium text-dark">Harga per GB Snapshot</label>
                    <input type="number" step="0.000001" min="0" name="price_per_snapshot_gb_hour" value="{{ old('price_per_snapshot_gb_hour', $product->price_per_snapshot_gb_hour) }}" class="form-control form-control-sm">
                  </div>
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-medium text-dark">Lisensi Windows per vCPU</label>
                    <input type="number" step="0.000001" min="0" name="price_windows_license_per_vcpu_hour" value="{{ old('price_windows_license_per_vcpu_hour', $product->price_windows_license_per_vcpu_hour) }}" class="form-control form-control-sm">
                  </div>
                </div>
              </div>
            </div>

            <p class="text-muted mt-3 mb-0" style="font-size:11px">
              <i class="fa-solid fa-circle-info"></i>
              OS &amp; aplikasi dipilih klien sendiri saat memesan — jadi satu paket ini berlaku untuk semua OS.
              Estimasi biaya modal bisa dilihat di halaman Diagnosa server.
            </p>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-2">Domain</h2>
          <select name="domain_option" class="form-select" style="{{ $selectStyle }}">
            <option value="none" @selected(old('domain_option', $product->domain_option ?? 'optional') === 'none')>Tidak terkait domain</option>
            <option value="optional" @selected(old('domain_option', $product->domain_option) === 'optional')>Opsional (boleh pakai domain sendiri)</option>
            <option value="required" @selected(old('domain_option', $product->domain_option) === 'required')>Wajib disertai domain</option>
          </select>
        </div>

        <div class="card border rounded-4 p-4">
          <h2 class="small fw-bold text-dark mb-2">Publikasi</h2>

          <label class="d-flex align-items-center gap-2 small text-dark mb-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="form-check-input" style="margin-top:0">
            Aktif (tampil di katalog)
          </label>
          <label class="d-flex align-items-center gap-2 small text-dark mb-3">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="form-check-input" style="margin-top:0">
            Tandai sebagai Unggulan
          </label>

          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Stok <span class="text-muted fw-normal">(opsional)</span></label>
            <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" class="form-control form-control-sm" placeholder="Kosongkan = tidak dibatasi">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="form-control form-control-sm">
          </div>

          <div class="d-flex flex-column gap-2 pt-2 border-top">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Produk</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
          </div>
        </div>
      </div>
    </form>
  @endif

  <script>
    (function () {
      const catSelect = document.getElementById('categorySelect');
      const serverSelect = document.getElementById('serverSelect');
      if (! catSelect || ! serverSelect) return;

      const vpsFields = document.getElementById('vpsSpecFields');
      const cpanelField = document.getElementById('cpanelPackageField');
      const billingModeSelect = document.getElementById('vmBillingMode');
      const pricingCard = document.getElementById('pricingCyclesCard');
      const hourlyCard = document.getElementById('hourlyPricingCard');
      const serverMeta = @json($vpsServerMeta);
      const componentFields = document.getElementById('componentSpecFields');
      const sizeNote = document.getElementById('sizeModelNote');
      const costBox = document.getElementById('vpsCostBox');
      const form = catSelect.form;

      // Simpan semua opsi server aslinya, supaya bisa disaring
      // bolak-balik tanpa kehilangan pilihan.
      const allServerOptions = Array.from(serverSelect.options).map(o => ({
        value: o.value, text: o.text, kind: o.dataset.kind || '',
      }));

      function currentType() {
        return catSelect.selectedOptions[0]?.dataset.type || 'hosting';
      }

      // Kartu "Harga per Siklus Tagihan" & kartu "Harga per Jam" cuma
      // relevan salah satu, tergantung Cara Menagih -- ditampilkan
      // gantian, bukan dua-duanya sekaligus dari awal seperti sebelumnya.
      function syncBillingMode() {
        const isVps = currentType() === 'vps';
        const isDeposit = isVps && billingModeSelect.value === 'deposit';

        pricingCard.classList.toggle('d-none', isDeposit);
        hourlyCard.classList.toggle('d-none', ! isDeposit);
      }

      function sync() {
        const isVps = currentType() === 'vps';
        const keep = serverSelect.value;

        // Saring pilihan server: kategori VPS hanya boleh server cloud,
        // kategori hosting hanya boleh server cPanel.
        serverSelect.innerHTML = '';
        allServerOptions
          .filter(o => o.value === '' || o.kind === (isVps ? 'vps' : 'hosting'))
          .forEach(function (o) {
            const opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.text;
            opt.dataset.kind = o.kind;
            if (o.value === keep) opt.selected = true;
            serverSelect.appendChild(opt);
          });

        vpsFields.classList.toggle('d-none', ! isVps);
        cpanelField.classList.toggle('d-none', isVps);

        // Field "Cara Menagih" cuma berlaku untuk produk VPS -- dinonaktifkan
        // (bukan cuma disembunyikan) untuk kategori hosting/domain supaya
        // TIDAK ikut ter-submit sama sekali, dan billing_mode produk hosting
        // selalu jatuh ke default "invoice" di server, bukan diam-diam
        // kebawa nilai "deposit" dari select yang kebetulan tersembunyi.
        billingModeSelect.disabled = ! isVps;

        syncBillingMode();
        syncProvider();
      }

      // Isian khusus provider server yang dipilih (mis. size/image/region
      // DigitalOcean) + mode spek: provider berbasis size menentukan
      // vCPU/RAM/disk dari size-nya, jadi isian komponen disembunyikan.
      function syncProvider() {
        const meta = currentType() === 'vps' ? serverMeta[serverSelect.value] : null;

        document.querySelectorAll('[data-provider-fields]').forEach(function (box) {
          const on = !! meta && box.dataset.providerFields === meta.driver;
          box.classList.toggle('d-none', ! on);
          box.querySelectorAll('input').forEach(function (i) { i.disabled = ! on; });
        });

        const sizeModel = !! meta && meta.model === 'size';
        componentFields.classList.toggle('d-none', sizeModel);
        componentFields.querySelectorAll('input,select').forEach(function (i) { i.disabled = sizeModel; });
        sizeNote.classList.toggle('d-none', ! sizeModel);

        fillDatalist('dlSizes', meta ? meta.sizes : {});
        fillDatalist('dlRegions', meta ? meta.regions : {});
        document.querySelectorAll('[data-provider-fields] input[data-source]').forEach(function (i) {
          const list = { sizes: 'dlSizes', regions: 'dlRegions' }[i.dataset.source];
          if (list) i.setAttribute('list', list); else i.removeAttribute('list');
        });

        estimate();
      }

      function fillDatalist(id, items) {
        const dl = document.getElementById(id);
        dl.innerHTML = '';
        Object.entries(items).forEach(function ([value, label]) {
          const opt = document.createElement('option');
          opt.value = value;
          opt.label = label;
          dl.appendChild(opt);
        });
      }

      // Estimasi harga modal provider (+ tarif jual per jam untuk mode
      // deposit) dari server -- rumusnya sama dengan yang dipakai saat
      // menyimpan & menagih, jadi tidak ada hitungan ganda di JavaScript.
      let estimateTimer = null;
      function estimate() {
        clearTimeout(estimateTimer);
        estimateTimer = setTimeout(runEstimate, 350);
      }

      function runEstimate() {
        if (currentType() !== 'vps' || ! serverMeta[serverSelect.value]) {
          costBox.classList.add('d-none');
          return;
        }

        const body = new FormData(form);
        body.delete('_method'); // form edit memakai PUT; endpoint estimasi cuma menerima POST

        fetch(@json(route('admin.products.vps-estimate')), {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: body,
        })
          .then(function (r) { return r.json(); })
          .then(renderEstimate)
          .catch(function () { costBox.classList.add('d-none'); });
      }

      const rp = function (n, d) { return 'Rp ' + Number(n).toLocaleString('id-ID', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); };

      function renderEstimate(res) {
        costBox.classList.remove('d-none');

        if (! res.ok) {
          costBox.style.color = '#b45309';
          costBox.textContent = res.message;
          return;
        }

        if (! res.ready) {
          costBox.style.color = '#b45309';
          costBox.textContent = ! res.synced
            ? 'Harga modal ' + res.provider + ' belum ditarik — tarik dulu di halaman Server.'
            : (res.fx_missing ? 'Kurs ' + res.currency + ' ke Rupiah belum diisi di halaman Server.' : 'Harga modal untuk spek ini tidak ditemukan — cek size/spek.');
          return;
        }

        costBox.style.color = '#334155';
        let html = '<b>Harga modal ' + res.provider + '</b>: ' + rp(res.modal_hourly, 2) + ' / jam · ± ' + rp(res.modal_monthly) + ' / bulan (730 jam)';

        if (res.sell_hourly !== null) {
          const below = res.sell_hourly > 0 && res.sell_hourly < res.modal_hourly;
          html += '<br><b>Tarif jual</b>: ' + rp(res.sell_hourly, 2) + ' / jam'
            + (res.sell_hourly <= 0 ? ' <span style="color:#b91c1c">— masih 0, produk tidak akan ditagih</span>' : '')
            + (below ? ' <span style="color:#b91c1c">— di bawah modal (rugi)</span>' : '');
        }

        costBox.innerHTML = html;
      }

      catSelect.addEventListener('change', sync);
      billingModeSelect.addEventListener('change', function () { syncBillingMode(); estimate(); });
      serverSelect.addEventListener('change', function () { syncBillingMode(); syncProvider(); });
      // Semua isian yang memengaruhi spek/tarif memicu estimasi ulang.
      form.addEventListener('input', estimate);
      form.addEventListener('change', estimate);
      sync();
    })();
  </script>

  <script>
    (function () {
      const radios = document.querySelectorAll('[data-product-pricing-mode]');
      const markupBox = document.getElementById('productMarkupFields');
      const manualBox = document.getElementById('productManualRateFields');
      if (! radios.length) return;

      function sync() {
        const mode = document.querySelector('[data-product-pricing-mode]:checked')?.value;
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
@endsection

