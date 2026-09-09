@extends('layouts.admin')

@section('title', 'Profil & Keamanan')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Profil &amp; Keamanan</h1>
    <p class="small text-muted mb-0">Kelola data akun dan pengaturan keamanan login Anda.</p>
  </div>

  <div class="row g-3" style="max-width:56rem">

    {{-- Profil --}}
    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3">Data Akun</h2>
        <form method="POST" action="{{ route('admin.profile.update') }}" class="d-flex flex-column gap-3">
          @csrf
          <div>
            <label class="form-label small fw-medium text-dark">Username</label>
            <input type="text" value="{{ $admin->username }}" class="form-control form-control-sm bg-light" disabled>
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Username tidak bisa diubah sendiri. Hubungi superadmin bila perlu.</p>
          </div>
          <div>
            <label class="form-label small fw-medium text-dark">Nama Lengkap</label>
            <input type="text" name="name" value="{{ old('name', $admin->name) }}" class="form-control form-control-sm" required>
            @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <div>
            <label class="form-label small fw-medium text-dark">Email</label>
            <input type="email" name="email" value="{{ old('email', $admin->email) }}" class="form-control form-control-sm" required>
            @error('email') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Kode verifikasi 2FA dikirim ke alamat ini.</p>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width:fit-content"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Profil</button>
        </form>
      </div>
    </div>

    {{-- Ganti password --}}
    <div class="col-12 col-lg-6">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3">Ganti Password</h2>
        <form method="POST" action="{{ route('admin.profile.password') }}" class="d-flex flex-column gap-3">
          @csrf
          <div>
            <label class="form-label small fw-medium text-dark">Password Saat Ini</label>
            <input type="password" name="current_password" class="form-control form-control-sm" required>
            @error('current_password') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <div>
            <label class="form-label small fw-medium text-dark">Password Baru</label>
            <div class="d-flex gap-2">
              <input type="password" name="password" id="pwField" class="form-control form-control-sm" required>
              <button type="button" onclick="lumoraGeneratePassword('pwField', 'pwConfirmField', 'pwChecklist')" class="btn btn-outline-secondary btn-sm text-nowrap flex-shrink-0">
                <i class="fa-solid fa-dice" style="font-size:11px"></i> Buatkan Otomatis
              </button>
            </div>
            @error('password') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            <ul id="pwChecklist" class="text-muted mt-2 mb-0 ps-0" style="font-size:11px;list-style:none"></ul>
          </div>
          <div>
            <label class="form-label small fw-medium text-dark">Konfirmasi Password Baru</label>
            <input type="password" name="password_confirmation" id="pwConfirmField" class="form-control form-control-sm" required>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width:fit-content"><i class="fa-solid fa-key" style="font-size:11px"></i> Ganti Password</button>
        </form>
      </div>
    </div>

    {{-- 2FA --}}
    <div class="col-12">
      <div class="card border rounded-4 p-4">
        <div class="d-flex align-items-start justify-content-between gap-4 flex-wrap">
          <div class="flex-grow-1" style="min-width:280px">
            <div class="d-flex align-items-center gap-2 mb-1">
              <h2 class="small fw-bold text-dark mb-0">Verifikasi Dua Langkah (2FA)</h2>
              <span class="badge {{ $admin->two_factor_enabled ? 'badge-soft-success' : 'badge-soft-secondary' }}">
                {{ $admin->two_factor_enabled ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
            <p class="text-muted mb-0" style="font-size:14px;line-height:1.6">
              Saat aktif, setiap login akan meminta kode 6 digit yang dikirim ke email
              <b class="text-dark">{{ $admin->email }}</b>. Ini melindungi akun Anda meski password bocor.
            </p>
            <p class="mt-2 mb-0" style="font-size:12px;color:#b45309">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Pastikan pengaturan email server (SMTP) sudah benar sebelum mengaktifkan —
              kalau email tidak terkirim, Anda tidak akan bisa login.
            </p>
          </div>

          <form method="POST" action="{{ route('admin.profile.two-factor') }}" class="w-100 d-flex flex-column gap-2" style="max-width:20rem">
            @csrf
            @if ($admin->two_factor_enabled)
              <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Password untuk menonaktifkan" required>
              @error('current_password') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="fa-solid fa-shield-halved" style="font-size:11px"></i> Nonaktifkan 2FA</button>
            @else
              <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-shield-halved" style="font-size:11px"></i> Aktifkan 2FA</button>
            @endif
          </form>
        </div>
      </div>
    </div>

    {{-- Info login terakhir --}}
    <div class="col-12">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Aktivitas Login Terakhir</h2>
        <div class="row g-3">
          <div class="col-sm-6">
            <p class="text-muted mb-0" style="font-size:11px">Waktu</p>
            <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $admin->last_login_at?->format('d M Y H:i') ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-0" style="font-size:11px">Alamat IP</p>
            <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $admin->last_login_ip ?? '—' }}</p>
          </div>
        </div>
      </div>
    </div>
    {{-- Push Notification --}}
    <div class="col-12">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-1">Notifikasi Push Browser</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Dapatkan notifikasi langsung di browser ini untuk tiket baru, pembayaran masuk, dan alert penting
          lainnya — tanpa perlu buka email atau refresh halaman terus-menerus.
        </p>
        <button type="button" id="pushToggleBtn" class="btn btn-outline-secondary btn-sm" style="width:fit-content" disabled>Memeriksa dukungan browser…</button>
      </div>
    </div>
  </div>

  <script src="{{ asset('assets/js/push-notifications.js') }}"></script>
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

      if (window.LumoraPush) {
        window.LumoraPush.attachToggle(document.getElementById('pushToggleBtn'), {
          vapidKey: '{{ route('admin.push.vapid-key') }}',
          subscribe: '{{ route('admin.push.subscribe') }}',
          unsubscribe: '{{ route('admin.push.unsubscribe') }}',
        });
      }
    });
  </script>

@endsection
