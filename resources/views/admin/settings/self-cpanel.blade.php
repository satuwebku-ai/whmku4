@extends('layouts.admin')
@section('title', 'cPanel Aplikasi Ini')

@section('content')

  @include('admin.settings._nav')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">cPanel Aplikasi Ini</h1>
    <p class="small text-muted mb-0">
      Akses cepat ke cPanel server tempat Lumora sendiri berjalan — untuk cek file, log, database, dst.
      Server ini akun cPanel biasa (bukan WHM), jadi login tetap manual — tapi kredensial &amp; tautan
      cepatnya disimpan di sini supaya tidak perlu dicari-cari lagi tiap kali.
    </p>
  </div>

  @php
    $url = \App\Models\Setting::get('self_cpanel_url');
    $username = \App\Models\Setting::get('self_cpanel_username');
    $password = \App\Models\Setting::get('self_cpanel_password');
    $sudahDiisi = filled($url) && filled($username);
  @endphp

  <div class="row g-4">
    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Kredensial</h2>
        <form method="POST" action="{{ route('admin.self-cpanel.update') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">URL Login cPanel</label>
            <input type="url" name="self_cpanel_url" value="{{ old('self_cpanel_url', $url) }}"
                   class="form-control form-control-sm" placeholder="https://beragam.kreasi.org:2083">
            @error('self_cpanel_url') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Username</label>
            <input type="text" name="self_cpanel_username" value="{{ old('self_cpanel_username', $username) }}"
                   class="form-control form-control-sm" placeholder="satuclou">
            @error('self_cpanel_username') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Password {{ $password ? '(sudah tersimpan, kosongkan jika tidak diganti)' : '' }}</label>
            <input type="password" name="self_cpanel_password" class="form-control form-control-sm"
                   placeholder="{{ $password ? '••••••••••••' : '' }}">
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Disimpan terenkripsi di database, sama seperti kredensial server lain.</p>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width:fit-content">Simpan</button>
        </form>
      </div>

      @if ($sudahDiisi)
        <div class="card border rounded-4 p-4 mt-4">
          <h2 class="small fw-bold text-dark mb-3">Login</h2>
          <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2 mb-2">
            <div class="min-w-0">
              <p class="text-muted mb-0" style="font-size:10px">Username</p>
              <p class="fw-medium text-dark mb-0" style="font-family:monospace;font-size:13px">{{ $username }}</p>
            </div>
            <button type="button" onclick="salinTeks(NAMA_USER_CPANEL, this)" class="btn btn-outline-secondary btn-sm">Salin</button>
          </div>

          @if ($password)
            <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2 mb-3">
              <div class="min-w-0">
                <p class="text-muted mb-0" style="font-size:10px">Password</p>
                <p class="fw-medium text-dark mb-0" style="font-family:monospace;font-size:13px" id="pwText">••••••••</p>
              </div>
              <div class="d-flex gap-1">
                <button type="button" onclick="tampilkanPassword()" class="btn btn-outline-secondary btn-sm" id="pwToggleBtn">Lihat</button>
                <button type="button" onclick="salinPassword(this)" class="btn btn-outline-secondary btn-sm">Salin</button>
              </div>
            </div>
          @endif

          <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-theme w-100">
            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:11px"></i> Buka Halaman Login cPanel
          </a>
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Login manual sekali di tab yang terbuka — setelah itu tautan Akses Cepat di samping bisa
            dipakai berulang tanpa diminta login lagi (selama sesi browser masih aktif).
          </p>
        </div>
      @endif
    </div>

    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-1">Akses Cepat</h2>
        @if (! $sudahDiisi)
          <p class="text-muted mb-0" style="font-size:13px">Isi URL &amp; username di samping dulu untuk mengaktifkan tautan cepat ini.</p>
        @else
          <p class="text-muted mb-3" style="font-size:11px">
            Login manual dulu lewat tombol di samping, baru tautan-tautan ini langsung menuju halamannya.
          </p>
          <div class="row g-2">
            @foreach ($shortcuts as $sc)
              <div class="col-4 col-sm-3">
                <a href="{{ rtrim($url, '/') }}/{{ $sc['path'] }}" target="_blank" rel="noopener"
                   class="d-flex flex-column align-items-center gap-2 p-2 rounded-3 border text-decoration-none text-center h-100">
                  <i class="fa-solid {{ $sc['icon'] }} text-muted" style="font-size:16px"></i>
                  <span class="text-muted" style="font-size:10px;line-height:1.2">{{ $sc['label'] }}</span>
                </a>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>

  <script>
    // Nilai password disisipkan SEKALI di sini lewat direktif json Blade
    // (aman dari karakter kutip/spesial apa pun), lalu dipakai ulang
    // kedua tombol -- bukan disisipkan langsung ke atribut onclick,
    // yang berisiko rusak kalau passwordnya mengandung tanda kutip.
    const RAHASIA_CPANEL = @json($password);
    const NAMA_USER_CPANEL = @json($username);

    function salinTeks(teks, btn) {
      navigator.clipboard.writeText(teks);
      const asli = btn.textContent;
      btn.textContent = 'Tersalin!';
      setTimeout(() => { btn.textContent = asli; }, 1200);
    }

    function salinPassword(btn) {
      salinTeks(RAHASIA_CPANEL, btn);
    }

    function tampilkanPassword() {
      const el = document.getElementById('pwText');
      const btn = document.getElementById('pwToggleBtn');
      const tersembunyi = el.textContent === '••••••••';
      el.textContent = tersembunyi ? RAHASIA_CPANEL : '••••••••';
      btn.textContent = tersembunyi ? 'Sembunyikan' : 'Lihat';
    }
  </script>

@endsection
