@extends('layouts.admin')

@section('title', 'Keamanan')

@section('content')

  @include('admin.settings._nav')

  @php use App\Models\Setting; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Keamanan</h1>
    <p class="small text-muted mb-0">Verifikasi robot dan verifikasi email pendaftar.</p>
  </div>

  <form method="POST" action="{{ route('admin.settings.security.update') }}" style="max-width:56rem">
    @csrf

    <div class="card border rounded-4 p-4 mb-3">
      <h2 class="small fw-bold text-dark mb-1">Verifikasi Robot (CAPTCHA)</h2>
      <p class="text-muted mb-3" style="font-size:12px">Melindungi form login dan pendaftaran dari bot penebak password.</p>

      <div class="mb-4">
        @foreach ([
          'adaptive' => ['Adaptif (disarankan)', 'Muncul hanya setelah 3 kali gagal dari IP yang sama. Pengguna normal tidak terganggu, bot tetap tersaring.'],
          'always'   => ['Selalu tampil', 'CAPTCHA di setiap login dan pendaftaran. Paling aman, tapi menambah langkah bagi semua orang.'],
          'off'      => ['Nonaktif', 'Tidak disarankan — form jadi terbuka untuk percobaan otomatis.'],
        ] as $key => [$judul, $ket])
          @php $isActive = Setting::get('captcha_mode', 'adaptive') === $key; @endphp
          <label class="d-flex align-items-start gap-2 rounded-3 border px-3 py-2 mb-2" style="cursor:pointer;{{ $isActive ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06)' : '' }}">
            <input type="radio" name="captcha_mode" value="{{ $key }}" @checked($isActive)
                   class="flex-shrink-0" style="margin-top:2px">
            <span>
              <span class="d-block small fw-medium text-dark">{{ $judul }}</span>
              <span class="d-block text-muted" style="font-size:11px">{{ $ket }}</span>
            </span>
          </label>
        @endforeach
      </div>

      <div class="pt-3 border-top">
        <div class="d-flex align-items-center gap-2 mb-1">
          <h3 class="small fw-medium text-dark mb-0">Google reCAPTCHA v2 <span class="text-muted fw-normal">(opsional)</span></h3>
          @php
            $recaptchaStatus = Setting::get('recaptcha_last_test_status');
            $recaptchaTestedAt = Setting::get('recaptcha_last_test_at');
          @endphp
          @if ($recaptchaStatus === 'success')
            <span class="badge badge-soft-success" title="Diuji {{ \Carbon\Carbon::parse($recaptchaTestedAt)->diffForHumans() }}">
              <i class="fa-solid fa-check" style="font-size:10px"></i> Success
            </span>
          @elseif ($recaptchaStatus === 'failed')
            <span class="badge badge-soft-danger" title="Diuji {{ \Carbon\Carbon::parse($recaptchaTestedAt)->diffForHumans() }}">
              <i class="fa-solid fa-xmark" style="font-size:10px"></i> Ditolak
            </span>
          @endif
        </div>
        <p class="text-muted mb-3" style="font-size:12px">
          Kalau kunci di bawah diisi, sistem memakai kotak centang "Saya bukan robot" dari Google.
          Kalau dikosongkan, dipakai pertanyaan hitungan sederhana bawaan — tetap efektif dan tidak
          bergantung layanan luar.
          Dapatkan kunci di <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener" class="text-accent">google.com/recaptcha/admin</a>
          (pilih tipe <b>reCAPTCHA v2 → Checkbox</b>).
        </p>

        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label small fw-medium text-dark">Site Key</label>
            <input type="text" name="recaptcha_site_key" value="{{ Setting::get('recaptcha_site_key') }}" class="form-control form-control-sm">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-medium text-dark">Secret Key {{ Setting::get('recaptcha_secret_key') ? '(kosongkan jika tidak diganti)' : '' }}</label>
            <input type="password" name="recaptcha_secret_key" class="form-control form-control-sm"
                   placeholder="{{ Setting::get('recaptcha_secret_key') ? '••••••••••••' : '' }}">
          </div>
        </div>

        <button type="submit" formaction="{{ route('admin.settings.security.test-recaptcha') }}" formnovalidate
                class="btn btn-outline-secondary btn-sm mt-3">
          <i class="fa-solid fa-plug" style="font-size:11px"></i> Coba Sambungkan
        </button>
        <p class="text-muted mt-1 mb-0" style="font-size:11px">
          Simpan dulu Secret Key-nya (tombol Simpan di bawah), baru klik ini untuk menguji ke API Google.
        </p>
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <h2 class="small fw-bold text-dark mb-1">Verifikasi Email Pendaftar</h2>
      <p class="text-muted mb-3" style="font-size:12px">Memastikan email yang didaftarkan benar-benar milik pendaftar.</p>

      <label class="d-flex align-items-start gap-2 rounded-3 border px-3 py-2">
        <input type="checkbox" name="require_email_verification" value="1"
               @checked(Setting::get('require_email_verification', '1') === '1')
               class="form-check-input flex-shrink-0" style="margin-top:2px">
        <span>
          <span class="d-block small fw-medium text-dark">Wajib verifikasi email sebelum bisa masuk</span>
          <span class="d-block text-muted" style="font-size:11px">
            Setelah mendaftar, klien menerima kode 6 digit dan harus memasukkannya sebelum akun bisa dipakai.
          </span>
        </span>
      </label>

      <div class="mt-3 rounded-3 px-3 py-2" style="background:#fffbeb;border:1px solid #fde68a;font-size:12px;color:#92400e">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Fitur ini bergantung penuh pada SMTP. Pastikan email server sudah berfungsi
        (<code>php artisan lumora:test-mail</code>) — kalau tidak, tidak ada klien baru yang bisa masuk.
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Pengaturan</button>
  </form>

@endsection
