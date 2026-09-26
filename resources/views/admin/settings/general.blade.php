@extends('layouts.admin')
@section('title', 'Pengaturan Umum')
@section('content')
  @include('admin.settings._nav')

  @php
    use App\Models\Setting;

    $brandingPresetGroups = [
      'logo' => 'Logo Lengkap (ikon + nama)',
      'icon' => 'Ikon Saja',
      'wordmark' => 'Teks Saja (tanpa ikon)',
      'favicon' => 'Favicon',
    ];
    $brandingPresetColors = [
      'indigo' => 'Indigo', 'blue' => 'Biru', 'emerald' => 'Emerald', 'teal' => 'Teal',
      'amber' => 'Amber', 'rose' => 'Rose', 'slate' => 'Slate', 'graywhite' => 'Abu-Putih', 'white' => 'Putih',
    ];
  @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Pengaturan Umum</h1>
    <p class="small text-muted mb-0">Identitas bisnis yang tampil di halaman publik dan email.</p>
  </div>

  <form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama Situs</label>
        <input type="text" name="site_name" value="{{ old('site_name', Setting::get('site_name', config('app.name'))) }}" class="form-control form-control-sm" required>
        @error('site_name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Tagline</label>
        <input type="text" name="site_tagline" value="{{ old('site_tagline', Setting::get('site_tagline')) }}" class="form-control form-control-sm" placeholder="Hosting cepat & terjangkau">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Perusahaan (untuk invoice)</label>
      <input type="text" name="company_name" value="{{ old('company_name', Setting::get('company_name')) }}" class="form-control form-control-sm" placeholder="PT Contoh Hosting">
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Email Support</label>
        <input type="email" name="support_email" value="{{ old('support_email', Setting::get('support_email')) }}" class="form-control form-control-sm" placeholder="support@contoh.com">
        @error('support_email') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Telepon Support</label>
        <input type="text" name="support_phone" value="{{ old('support_phone', Setting::get('support_phone')) }}" class="form-control form-control-sm" placeholder="+62 811 2345 678">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Alamat Perusahaan</label>
      <textarea name="company_address" rows="2" class="form-control form-control-sm">{{ old('company_address', Setting::get('company_address')) }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Teks Footer</label>
      <input type="text" name="footer_text" value="{{ old('footer_text', Setting::get('footer_text')) }}" class="form-control form-control-sm" placeholder="© {{ date('Y') }} Nama Perusahaan. Semua hak dilindungi.">
    </div>

    {{-- Identitas visual --}}
    <div class="pt-3 border-top">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="small fw-bold text-dark mb-0">Identitas Visual</h2>
        <a href="{{ route('admin.settings.branding-diagnostics') }}" target="_blank" class="text-decoration-none text-accent" style="font-size:12px">
          <i class="fa-solid fa-stethoscope" style="font-size:10px"></i> Logo tidak muncul? Cek di sini
        </a>
      </div>

      @php
        $logo = Setting::get('site_logo');
        $favicon = Setting::get('site_favicon');
      @endphp

      {{-- Galeri Preset -- gambar dilayani lewat rute (bukan base64 ditanam di halaman), jadi ukuran halaman tetap kecil --}}
      <div class="mb-4">
        <label class="form-label small fw-medium text-dark">Pilih dari Galeri Logo</label>
        <p class="text-muted mb-2" style="font-size:11px">Klik "Pakai" pada varian yang kamu mau -- langsung aktif, tidak perlu upload file sendiri.</p>

        @foreach ($brandingPresetGroups as $groupKey => $groupLabel)
          @php
            $defaultTarget = $groupKey === 'favicon' ? 'site_favicon' : ($groupKey === 'icon' ? 'site_icon' : 'site_logo');
          @endphp
          <p class="fw-medium text-dark mb-2 mt-3" style="font-size:12px">{{ $groupLabel }}</p>
          <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach ($brandingPresetColors as $colorKey => $colorLabel)
              <div class="border rounded-3 p-2 text-center preset-swatch" style="width:104px">
                <img src="{{ route('admin.settings.general.preset-image', [$groupKey, $colorKey]) }}" alt="{{ $colorLabel }}" loading="lazy" class="mb-1" style="width:100%;height:36px;object-fit:contain;background:#f8fafc;border-radius:.25rem">
                <p class="text-muted mb-1" style="font-size:9px">{{ $colorLabel }}</p>
                <select class="form-select form-select-sm preset-target-select mb-1" style="font-size:9px;padding:.1rem .25rem">
                  <option value="site_logo" @selected($defaultTarget === 'site_logo')>Logo Utama</option>
                  <option value="site_icon" @selected($defaultTarget === 'site_icon')>Ikon Kecil</option>
                  <option value="site_favicon" @selected($defaultTarget === 'site_favicon')>Favicon</option>
                </select>
                <button type="button" class="btn btn-outline-secondary w-100 use-preset-btn"
                        style="font-size:9px;padding:.15rem .3rem"
                        data-group="{{ $groupKey }}"
                        data-color="{{ $colorKey }}"
                        data-preset-label="{{ $groupLabel }} - {{ $colorLabel }}">
                  Pakai
                </button>
              </div>
            @endforeach
          </div>
        @endforeach

        <input type="hidden" id="presetCsrfToken" value="{{ csrf_token() }}">
        <p id="presetStatus" class="mb-0 mt-2" style="font-size:11px;min-height:14px"></p>
      </div>

      <div class="d-flex align-items-center gap-2 my-4">
        <hr class="flex-grow-1 m-0">
        <span class="text-muted" style="font-size:11px">atau upload logo sendiri</span>
        <hr class="flex-grow-1 m-0">
      </div>

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Logo</label>
        @if ($logo)
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="{{ route('branding.file', $logo) }}" alt="Logo" class="rounded-3" style="height:40px;background:#f1f5f9;padding:6px 12px;object-fit:contain">
            <label class="d-flex align-items-center gap-2 text-danger" style="font-size:12px">
              <input type="checkbox" name="remove_site_logo" value="1" class="form-check-input" style="margin-top:0">
              Hapus logo
            </label>
          </div>
        @endif
        <input type="file" name="site_logo" accept="image/*" class="form-control form-control-sm">
        @error('site_logo') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">
          PNG/SVG dengan latar transparan, tinggi ideal 40–60px, maks 1 MB.
          Kalau kosong, nama situs ditampilkan sebagai teks.
        </p>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Tampilan di Sebelah Logo</label>
        @php $brandingDisplay = Setting::get('branding_display', 'logo_and_text'); @endphp
        <div class="row g-2" id="brandingDisplayGroup">
          @foreach (['logo_and_text' => 'Logo + Nama', 'logo_only' => 'Logo Saja', 'text_only' => 'Nama Saja'] as $key => $label)
            <div class="col-4">
              <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100"
                     style="cursor:pointer;{{ $brandingDisplay === $key ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
                <input type="radio" name="branding_display" value="{{ $key }}" @checked($brandingDisplay === $key) class="d-none" data-branding-radio>
                {{ $label }}
              </label>
            </div>
          @endforeach
        </div>
        <p class="text-muted mt-1 mb-0" style="font-size:11px">
          Berlaku di panel admin dan halaman login. "Logo Saja" cocok kalau logomu sudah memuat nama merek di dalam gambarnya.
        </p>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Favicon</label>
        @if ($favicon)
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="{{ route('branding.file', $favicon) }}" alt="Favicon" class="rounded-2" style="width:32px;height:32px;background:#f1f5f9;padding:4px;object-fit:contain">
            <label class="d-flex align-items-center gap-2 text-danger" style="font-size:12px">
              <input type="checkbox" name="remove_site_favicon" value="1" class="form-check-input" style="margin-top:0">
              Hapus favicon
            </label>
          </div>
        @endif
        <input type="file" name="site_favicon" accept="image/*" class="form-control form-control-sm">
        @error('site_favicon') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Ikon di tab browser. PNG 32×32 atau 64×64, maks 256 KB.</p>
      </div>

      <div>
        <label class="form-label small fw-medium text-dark">Warna Tema</label>
        <div class="d-flex align-items-center gap-3">
          <input type="color" name="theme_color" value="{{ old('theme_color', Setting::get('theme_color', '#6366F1')) }}"
                 class="rounded-3 border" style="width:56px;height:40px;cursor:pointer;padding:2px">
          <span class="text-muted" style="font-size:12px">Dipakai untuk tombol dan aksen di halaman publik.</span>
        </div>
        @error('theme_color') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Pengaturan</button>
  </form>

  <script>
    document.querySelectorAll('[data-branding-radio]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        document.querySelectorAll('[data-branding-radio]').forEach(function (r) {
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
      });
    });

    document.querySelectorAll('.use-preset-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const status = document.getElementById('presetStatus');
        const swatch = btn.closest('.preset-swatch');
        const targetSelect = swatch.querySelector('.preset-target-select');
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = '...';
        status.textContent = '';

        fetch('{{ route('admin.settings.general.preset-branding') }}', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.getElementById('presetCsrfToken').value,
          },
          body: new URLSearchParams({
            group: btn.dataset.group,
            color: btn.dataset.color,
            target: targetSelect.value,
          }),
        })
          .then(function (res) {
            return res.json().then(function (body) {
              if (! res.ok) throw new Error(body.message || ('HTTP ' + res.status));
              return body;
            });
          })
          .then(function (body) {
            status.textContent = '✓ ' + (body.message || 'Berhasil disimpan.') + ' Memuat ulang halaman...';
            status.style.color = '#15803d';
            setTimeout(() => window.location.reload(), 700);
          })
          .catch(function (err) {
            status.textContent = 'Gagal: ' + err.message;
            status.style.color = '#b91c1c';
            btn.disabled = false;
            btn.textContent = original;
          });
      });
    });
  </script>
@endsection