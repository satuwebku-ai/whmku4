@extends('layouts.admin')

@section('title', 'Tampilan Notifikasi')

@section('content')

  @include('admin.settings._nav')

  @php
    use App\Support\ToastStyle;
    $v = fn (string $key) => old($key, $values[$key]);
    // Setelah validasi gagal, checkbox yang tidak dicentang tidak ada di old().
    $checked = fn (string $key) => count((array) old()) > 0 ? old($key) === '1' : $values[$key] === '1';
    $samples = [
      'success' => 'Pengaturan berhasil disimpan.',
      'error' => 'Gagal menyimpan. Periksa isian Anda.',
      'warning' => 'Saldo klien hampir habis.',
      'info' => 'Ada 3 tiket baru yang belum dibaca.',
    ];
    $icons = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
  @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Tampilan Notifikasi</h1>
    <p class="small text-muted mb-0">Atur posisi, durasi, ukuran, dan warna pesan pop-up (sukses, error, peringatan, info) di seluruh panel admin. Perubahan langsung terlihat di sini sebelum disimpan.</p>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-7">
      <form method="POST" action="{{ route('admin.settings.toast.update') }}" id="toastForm">
        @csrf

        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-1">Gaya Cepat</h2>
          <p class="text-muted mb-3" style="font-size:12px">Pilih salah satu sebagai titik awal, lalu sesuaikan warnanya di bawah.</p>
          <div class="d-flex flex-wrap gap-2">
            @foreach (ToastStyle::PRESETS as $key => $preset)
              <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="{{ $key }}">{{ $preset['label'] }}</button>
            @endforeach
          </div>
        </div>

        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-3">Posisi & Perilaku</h2>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Posisi di layar</label>
              <select name="toast_position" class="form-control form-control-sm">
                @foreach (ToastStyle::POSITIONS as $key => $label)
                  <option value="{{ $key }}" @selected($v('toast_position') === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Lebar (px)</label>
              <input type="number" name="toast_width" min="260" max="640" step="10" value="{{ $v('toast_width') }}" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Lama tampil: sukses & info (detik)</label>
              <input type="number" name="toast_duration" min="1" max="60" value="{{ $v('toast_duration') }}" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Lama tampil: error & peringatan (detik)</label>
              <input type="number" name="toast_duration_error" min="1" max="60" value="{{ $v('toast_duration_error') }}" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-medium text-dark">Kelengkungan sudut (px)</label>
              <input type="number" name="toast_radius" min="0" max="32" value="{{ $v('toast_radius') }}" class="form-control form-control-sm">
            </div>
          </div>

          <div class="d-flex flex-wrap gap-4 mt-3">
            <label class="d-flex align-items-center gap-2 small text-dark" style="cursor:pointer">
              <input type="checkbox" name="toast_show_icon" value="1" @checked($checked('toast_show_icon'))> Tampilkan ikon
            </label>
            <label class="d-flex align-items-center gap-2 small text-dark" style="cursor:pointer">
              <input type="checkbox" name="toast_show_progress" value="1" @checked($checked('toast_show_progress'))> Garis waktu di bawah
            </label>
            <label class="d-flex align-items-center gap-2 small text-dark" style="cursor:pointer">
              <input type="checkbox" name="toast_shadow" value="1" @checked($checked('toast_shadow'))> Bayangan
            </label>
          </div>
        </div>

        <div class="card border rounded-4 p-4 mb-3">
          <h2 class="small fw-bold text-dark mb-1">Warna</h2>
          <p class="text-muted mb-3" style="font-size:12px">Latar sengaja berupa warna penuh (bukan transparan) supaya teks selalu terbaca di atas halaman apa pun.</p>

          @foreach (ToastStyle::TYPES as $type => $typeLabel)
            <div class="d-flex flex-wrap align-items-center gap-3 py-2 {{ ! $loop->first ? 'border-top' : '' }}">
              <span class="small fw-semibold text-dark" style="width:90px">{{ $typeLabel }}</span>
              @foreach (ToastStyle::COLOR_PARTS as $part => $partLabel)
                @php $name = "toast_{$type}_{$part}"; @endphp
                <label class="d-flex align-items-center gap-2 small text-muted mb-0" style="cursor:pointer">
                  <input type="color" name="{{ $name }}" value="{{ $v($name) }}" style="width:34px;height:28px;padding:0;border:1px solid #dee2e6;border-radius:6px;background:#fff;cursor:pointer">
                  <span>{{ $partLabel }} <span class="font-monospace" data-hex-for="{{ $name }}">{{ $v($name) }}</span></span>
                </label>
                @error($name)<span class="text-danger small">{{ $message }}</span>@enderror
              @endforeach
            </div>
          @endforeach
        </div>

        @if ($errors->any())
          <div class="small text-danger mb-3">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
          </div>
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2">
          <button type="submit" class="btn btn-primary">Simpan Tampilan</button>
          <button type="button" class="btn btn-outline-secondary" data-reset-form>Batalkan Perubahan</button>
        </div>
      </form>

      <form method="POST" action="{{ route('admin.settings.toast.reset') }}" class="mt-3"
            data-confirm="Semua pengaturan tampilan notifikasi dikembalikan ke bawaan. Lanjutkan?"
            data-confirm-title="Kembalikan ke bawaan" data-confirm-style="warn" data-confirm-label="Ya, kembalikan">
        @csrf
        <button type="submit" class="btn btn-link btn-sm p-0 text-muted">Kembalikan semua ke bawaan</button>
      </form>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card border rounded-4 p-4" style="position:sticky; top:5rem">
        <h2 class="small fw-bold text-dark mb-1">Pratinjau</h2>
        <p class="text-muted mb-3" style="font-size:12px">Tekan tombol di bawah untuk memunculkan notifikasi sungguhan di posisi yang dipilih.</p>

        <div id="toastPreview" class="d-flex flex-column gap-2 mb-3 p-3 rounded-3" style="background:#f1f5f9">
          @foreach ($samples as $type => $text)
            <div class="lumora-toast is-{{ $type }}" style="animation:none; width:100%">
              <i class="fa-solid {{ $icons[$type] }} lt-icon"></i>
              <span class="lt-msg">{{ $text }}</span>
              <button type="button" class="lt-close" tabindex="-1" aria-hidden="true"><i class="fa-solid fa-xmark"></i></button>
              <span class="lt-bar" style="animation:none; transform:scaleX(.6)"></span>
            </div>
          @endforeach
        </div>

        <div class="d-flex flex-wrap gap-2">
          @foreach (ToastStyle::TYPES as $type => $label)
            <button type="button" class="btn btn-outline-secondary btn-sm" data-test-toast="{{ $type }}">Uji {{ strtolower($label) }}</button>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <script @nonce>
    (function () {
      const form = document.getElementById('toastForm');
      const wrap = document.getElementById('toastWrap');
      const preview = document.getElementById('toastPreview');
      const cfg = window.lumoraToastConfig;
      const root = document.documentElement.style;
      const presets = {!! json_encode(collect(ToastStyle::PRESETS)->map(fn ($p) => collect($p)->except('label'))) !!};
      const types = ['success', 'error', 'warning', 'info'];
      const parts = ['bg', 'border', 'text'];
      const initial = new FormData(form);
      const samples = {!! json_encode($samples) !!};

      const field = (name) => form.elements[name];

      function apply() {
        root.setProperty('--lt-width', field('toast_width').value + 'px');
        root.setProperty('--lt-radius', field('toast_radius').value + 'px');
        root.setProperty('--lt-shadow', field('toast_shadow').checked
          ? '0 10px 30px rgba(15,23,42,.18), 0 2px 6px rgba(15,23,42,.08)' : 'none');

        types.forEach((t) => parts.forEach((p, i) => {
          const el = field('toast_' + t + '_' + p);
          root.setProperty('--lt-' + t + '-' + p, el.value);
          const hex = form.querySelector('[data-hex-for="toast_' + t + '_' + p + '"]');
          if (hex) hex.textContent = el.value;
        }));

        wrap.className = 'lumora-toast-wrap lt-pos-' + field('toast_position').value;

        cfg.duration = (parseInt(field('toast_duration').value, 10) || 4) * 1000;
        cfg.durationError = (parseInt(field('toast_duration_error').value, 10) || 8) * 1000;
        cfg.showIcon = field('toast_show_icon').checked;
        cfg.showProgress = field('toast_show_progress').checked;

        preview.querySelectorAll('.lt-icon').forEach((el) => { el.style.display = cfg.showIcon ? '' : 'none'; });
        preview.querySelectorAll('.lt-bar').forEach((el) => { el.style.display = cfg.showProgress ? '' : 'none'; });
      }

      form.addEventListener('input', apply);
      form.addEventListener('change', apply);

      document.querySelectorAll('[data-preset]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const preset = presets[btn.dataset.preset];
          types.forEach((t) => parts.forEach((p, i) => { field('toast_' + t + '_' + p).value = preset[t][i]; }));
          apply();
        });
      });

      document.querySelector('[data-reset-form]').addEventListener('click', () => {
        form.reset();
        apply();
      });

      document.querySelectorAll('[data-test-toast]').forEach((btn) => {
        btn.addEventListener('click', () => window.lumoraToast(btn.dataset.testToast, samples[btn.dataset.testToast]));
      });

      apply();
    })();
  </script>
@endsection
