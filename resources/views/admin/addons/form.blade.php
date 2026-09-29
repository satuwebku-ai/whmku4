@extends('layouts.admin')

@section('title', $addon->exists ? 'Edit Addon' : 'Tambah Addon')

@section('content')

  <a href="{{ route('admin.addons.index') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Addons</a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-4">{{ $addon->exists ? 'Edit Addon' : 'Tambah Addon' }}</h1>

  <form method="POST" action="{{ $addon->exists ? route('admin.addons.update', $addon) : route('admin.addons.store') }}" style="max-width:42rem">
    @csrf
    @if ($addon->exists) @method('PUT') @endif

    <div class="card border rounded-4 p-4 mb-3">
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Nama Addon</label>
        <input type="text" name="name" id="nameInput" value="{{ old('name', $addon->name) }}" class="form-control form-control-sm" placeholder="mis. IP Dedicated" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Slug <span class="text-muted fw-normal">(opsional, otomatis dari nama kalau kosong)</span></label>
        <input type="text" name="slug" id="slugInput" value="{{ old('slug', $addon->slug) }}" class="form-control form-control-sm" placeholder="ip-dedicated">
        @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Kategori</label>
          <select name="category" class="form-select form-select-sm">
            @foreach (\App\Models\Addon::CATEGORIES as $key => $label)
              <option value="{{ $key }}" @selected(old('category', $addon->category ?: 'license') === $key)>{{ $label }}</option>
            @endforeach
          </select>
          @error('category') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Merek</label>
          <input type="text" name="brand" value="{{ old('brand', $addon->brand) }}" class="form-control form-control-sm" placeholder="mis. Sectigo, GeoTrust, cPanel">
          @error('brand') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Ringkasan <span class="text-muted fw-normal">(satu kalimat, tampil di kartu katalog)</span></label>
        <input type="text" name="summary" value="{{ old('summary', $addon->summary) }}" class="form-control form-control-sm" maxlength="255">
        @error('summary') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="form-label small fw-medium text-dark">Deskripsi</label>
        <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Dijelaskan singkat ke klien saat memilih addon ini.">{{ old('description', $addon->description) }}</textarea>
        @error('description') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark mb-1">Keterangan Produk Lengkap <span class="text-muted fw-normal">(tampil di halaman detail)</span></label>
      <p class="text-muted mb-3" style="font-size:11px">Semua isian di bawah opsional. Isi satu item per baris.</p>
      <div class="mb-3">
        <label class="text-muted mb-1 d-block" style="font-size:11px">Penjelasan panjang</label>
        <textarea name="long_description" rows="4" class="form-control form-control-sm">{{ old('long_description', $addon->long_description) }}</textarea>
      </div>
      <div class="mb-3">
        <label class="text-muted mb-1 d-block" style="font-size:11px">Fitur / keunggulan (satu per baris)</label>
        <textarea name="features_text" rows="5" class="form-control form-control-sm" placeholder="Enkripsi 256-bit&#10;Terbit dalam hitungan menit">{{ old('features_text', implode("\n", $addon->features ?? [])) }}</textarea>
      </div>
      <div class="mb-3">
        <label class="text-muted mb-1 d-block" style="font-size:11px">Spesifikasi (format <code>Label: nilai</code>, satu per baris)</label>
        <textarea name="specs_text" rows="5" class="form-control form-control-sm" placeholder="Tipe validasi: Domain Validation (DV)&#10;Cakupan domain: 1 nama domain">{{ old('specs_text', collect($addon->specs ?? [])->map(fn ($v, $k) => "$k: $v")->implode("\n")) }}</textarea>
      </div>
      <div>
        <label class="text-muted mb-1 d-block" style="font-size:11px">FAQ (format <code>Pertanyaan | Jawaban</code>, satu per baris)</label>
        <textarea name="faqs_text" rows="5" class="form-control form-control-sm">{{ old('faqs_text', collect($addon->faqs ?? [])->map(fn ($x) => $x['q'].' | '.$x['a'])->implode("\n")) }}</textarea>
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark mb-3">Harga Jual per Siklus <span class="text-muted fw-normal">(kosongkan kalau tidak ditawarkan untuk siklus itu)</span></label>
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Bulanan</label>
          <input type="number" step="1" min="0" name="price_monthly" value="{{ old('price_monthly', $addon->price_monthly) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">3 Bulan</label>
          <input type="number" step="1" min="0" name="price_quarterly" value="{{ old('price_quarterly', $addon->price_quarterly) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">6 Bulan</label>
          <input type="number" step="1" min="0" name="price_semi_annually" value="{{ old('price_semi_annually', $addon->price_semi_annually) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Tahunan</label>
          <input type="number" step="1" min="0" name="price_annually" value="{{ old('price_annually', $addon->price_annually) }}" class="form-control form-control-sm">
        </div>
      </div>
      <p class="text-muted mt-3 mb-0" style="font-size:11px">
        Addon otomatis ikut ditagih di invoice perpanjangan layanan hosting yang memasangnya — mengikuti
        siklus tagihan layanan itu sendiri. Kalau siklus layanan tidak punya harga di sini, addon tidak
        bisa dipasang untuk layanan dengan siklus itu.
      </p>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
          <label class="form-label small fw-medium text-dark mb-1">Harga Modal Supplier</label>
          <p class="text-muted mb-0" style="font-size:11px">Diisi manual sekarang, atau diperbarui dari API supplier. Data ini hanya untuk perhitungan internal dan tidak ditampilkan ke klien.</p>
        </div>
        <select name="pricing_source" class="form-select form-select-sm" style="width:130px">
          <option value="manual" @selected(old('pricing_source', $addon->pricing_source ?: 'manual') === 'manual')>Manual</option>
          <option value="api" @selected(old('pricing_source', $addon->pricing_source) === 'api')>API Supplier</option>
        </select>
      </div>
      <div class="row g-3">
        @foreach (['monthly' => 'Bulanan', 'quarterly' => '3 Bulan', 'semi_annually' => '6 Bulan', 'annually' => 'Tahunan'] as $cycle => $label)
          <div class="col-sm-6">
            <label class="text-muted mb-1 d-block" style="font-size:11px">{{ $label }}</label>
            <input type="number" step="1" min="0" name="cost_price_{{ $cycle }}" value="{{ old('cost_price_'.$cycle, $addon->{'cost_price_'.$cycle}) }}" class="form-control form-control-sm" placeholder="Harga modal">
          </div>
        @endforeach
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark mb-1">Konfigurasi API Supplier</label>
      <p class="text-muted mb-3" style="font-size:11px">Endpoint JSON generik. Isi path JSON, misalnya <code>data.prices.monthly</code>. Token disimpan terenkripsi.</p>
      <div class="row g-3">
        <div class="col-12">
          <label class="text-muted mb-1 d-block" style="font-size:11px">URL Endpoint</label>
          <input type="url" name="supplier_api_url" value="{{ old('supplier_api_url', $addon->supplier_api_url) }}" class="form-control form-control-sm" placeholder="https://supplier.example/api/prices">
        </div>
        <div class="col-sm-4">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Metode</label>
          <select name="supplier_http_method" class="form-select form-select-sm">
            <option value="GET" @selected(old('supplier_http_method', $addon->supplier_http_method ?: 'GET') === 'GET')>GET</option>
            <option value="POST" @selected(old('supplier_http_method', $addon->supplier_http_method) === 'POST')>POST</option>
          </select>
        </div>
        <div class="col-sm-8">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Bearer Token <span class="fw-normal">(kosongkan saat edit untuk mempertahankan)</span></label>
          <input type="password" name="supplier_api_token" value="" class="form-control form-control-sm" autocomplete="new-password">
        </div>
        @foreach (['monthly' => 'Path harga bulanan', 'quarterly' => 'Path harga 3 bulan', 'semi_annually' => 'Path harga 6 bulan', 'annually' => 'Path harga tahunan'] as $cycle => $label)
          <div class="col-sm-6">
            <label class="text-muted mb-1 d-block" style="font-size:11px">{{ $label }}</label>
            <input type="text" name="supplier_price_path_{{ $cycle }}" value="{{ old('supplier_price_path_'.$cycle, $addon->{'supplier_price_path_'.$cycle}) }}" class="form-control form-control-sm" placeholder="data.prices.{{ $cycle }}">
          </div>
        @endforeach
      </div>
      @if ($addon->supplier_last_synced_at)
        <p class="text-muted mt-3 mb-0" style="font-size:11px">Sync terakhir: {{ $addon->supplier_last_synced_at->format('d M Y H:i') }}</p>
      @endif
      @if ($addon->supplier_last_error)
        <p class="text-danger mt-2 mb-0" style="font-size:11px">{{ $addon->supplier_last_error }}</p>
      @endif
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
          <input type="number" name="sort_order" value="{{ old('sort_order', $addon->sort_order ?? 0) }}" min="0" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="d-flex align-items-center gap-2 small text-dark mb-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $addon->is_active ?? true)) class="form-check-input" style="margin-top:0">
            Aktif (bisa dipasang klien)
          </label>
          <label class="d-flex align-items-center gap-2 small text-dark mb-2">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $addon->is_public ?? true)) class="form-check-input" style="margin-top:0">
            Tampilkan di katalog Lisensi publik
          </label>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
      @if ($addon->exists && $addon->isApiPricing())
        <button type="submit" form="sync-addon-form" class="btn btn-outline-primary btn-sm">Sync Harga Modal</button>
      @endif
      <a href="{{ route('admin.addons.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>
  @if ($addon->exists && $addon->isApiPricing())
    <form id="sync-addon-form" method="POST" action="{{ route('admin.addons.sync', $addon) }}" class="d-none">@csrf</form>
  @endif

  <script>
    (function () {
      const name = document.getElementById('nameInput');
      const slug = document.getElementById('slugInput');

      const slugify = (s) => s.toLowerCase().trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

      let slugTouched = slug.value.length > 0;
      slug.addEventListener('input', () => { slugTouched = true; });
      name.addEventListener('input', () => {
        if (!slugTouched) slug.value = slugify(name.value);
      });
    })();
  </script>

@endsection
