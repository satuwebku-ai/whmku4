@extends('client.layout')
@section('title', 'Profil Saya')

@section('content')
  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Profil Saya</h1>
    <p class="text-muted mb-0">Perbarui data akun dan password Anda.</p>
  </div>

  @if ($client->google_id)
    <div class="card-public p-3 mb-4 d-flex align-items-center gap-3" style="border-color:#c7d2fe!important;background:rgba(79,70,229,.04)">
      @if ($client->avatar)
        <img src="{{ $client->avatar }}" alt="{{ $client->name }}" class="rounded-circle" style="width:40px;height:40px;object-fit:cover">
      @endif
      <p class="text-muted mb-0" style="font-size:12px">
        <i class="fa-brands fa-google text-theme"></i>
        Akun ini tertaut dengan Google (<b class="text-dark">{{ $client->email }}</b>). Anda tetap bisa mengatur password
        di bawah kalau ingin bisa masuk tanpa Google juga.
      </p>
    </div>
  @endif

  <div class="row g-4">

    <div class="col-12 col-lg-6">
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Data Akun</h2>
        <form method="POST" action="{{ route('client.profile.update') }}" class="d-flex flex-column gap-3">
          @csrf

          <div>
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" value="{{ old('name', $client->name) }}" required class="form-control">
            @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>

          <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $client->email) }}" required class="form-control">
            @error('email') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>

          <div>
            <label class="form-label">No. WhatsApp / Telepon</label>
            <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" required class="form-control">
            @error('phone') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>

          <div>
            <label class="form-label">Perusahaan</label>
            <input type="text" name="company" value="{{ old('company', $client->company) }}" class="form-control">
          </div>

          <div>
            <label class="form-label">Alamat</label>
            <input type="text" name="address" value="{{ old('address', $client->address) }}" class="form-control">
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Kota</label>
              <input type="text" name="city" value="{{ old('city', $client->city) }}" class="form-control">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Negara</label>
              <input type="text" name="country" value="{{ old('country', $client->country) }}" class="form-control">
            </div>
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Provinsi <span class="text-muted fw-normal">(untuk registrasi domain)</span></label>
              <input type="text" name="state" value="{{ old('state', $client->state) }}" placeholder="DKI Jakarta" class="form-control">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Kode Pos</label>
              <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}" class="form-control">
            </div>
          </div>

          {{-- Preferensi notifikasi --}}
          <div class="pt-3 border-top">
            <h3 class="fw-semibold text-dark mb-1" style="font-size:14px">Notifikasi</h3>
            <p class="text-muted mb-3" style="font-size:12px">
              Email tagihan, pembayaran, dan tiket selalu dikirim karena bagian dari layanan.
              Yang di bawah ini bisa Anda atur sendiri.
            </p>

            <div class="d-flex flex-column gap-3">
              <div>
                <label class="form-label">Nomor WhatsApp <span class="text-muted fw-normal">(opsional)</span></label>
                <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $client->whatsapp_number) }}"
                       placeholder="081234567890" class="form-control">
                @error('whatsapp_number') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              </div>

              <label class="d-flex align-items-start gap-3 rounded-3 border px-3 py-3">
                <input type="checkbox" name="notify_whatsapp" value="1" @checked(old('notify_whatsapp', $client->notify_whatsapp))
                       class="form-check-input flex-shrink-0" style="margin-top:2px">
                <span>
                  <span class="d-block fw-medium text-dark" style="font-size:14px">Terima notifikasi lewat WhatsApp</span>
                  <span class="d-block text-muted" style="font-size:12px">Tagihan dan info layanan dikirim juga ke WhatsApp. Butuh nomor di atas terisi.</span>
                </span>
              </label>

              <label class="d-flex align-items-start gap-3 rounded-3 border px-3 py-3">
                <input type="checkbox" name="notify_sms" value="1" @checked(old('notify_sms', $client->notify_sms))
                       class="form-check-input flex-shrink-0" style="margin-top:2px">
                <span>
                  <span class="d-block fw-medium text-dark" style="font-size:14px">Terima notifikasi lewat SMS</span>
                  <span class="d-block text-muted" style="font-size:12px">Cuma untuk hal mendesak (kode login, tagihan, layanan disuspend) — dikirim ke nomor telepon di atas.</span>
                </span>
              </label>

              <label class="d-flex align-items-start gap-3 rounded-3 border px-3 py-3">
                <input type="checkbox" name="notify_promo" value="1" @checked(old('notify_promo', $client->notify_promo))
                       class="form-check-input flex-shrink-0" style="margin-top:2px">
                <span>
                  <span class="d-block fw-medium text-dark" style="font-size:14px">Terima info promo dan penawaran</span>
                  <span class="d-block text-muted" style="font-size:12px">Hilangkan centang untuk berhenti menerima email promosi.</span>
                </span>
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-theme" style="width:fit-content"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Perubahan</button>
        </form>
      </div>

      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-1">Notifikasi Push Browser</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Dapatkan notifikasi langsung di browser ini (tanpa perlu buka email) untuk tagihan baru, balasan tiket,
          dan info layanan lainnya — gratis, dan bisa dimatikan kapan saja.
        </p>
        <button type="button" id="pushToggleBtn" class="btn btn-outline-secondary btn-sm" disabled>Memeriksa dukungan browser…</button>
      </div>
    </div>

    <div class="col-12 col-lg-6 d-flex flex-column gap-4">
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Ganti Password</h2>
        <form method="POST" action="{{ route('client.profile.password') }}" class="d-flex flex-column gap-3">
          @csrf

          <div>
            <label class="form-label">Password Saat Ini</label>
            <input type="password" name="current_password" required class="form-control">
            @error('current_password') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>

          <div>
            <label class="form-label">Password Baru</label>
            <div class="d-flex gap-2">
              <input type="password" name="password" id="pwField" required class="form-control">
              <button type="button" onclick="lumoraGeneratePassword('pwField', 'pwConfirmField', 'pwChecklist')" class="btn btn-outline-secondary text-nowrap flex-shrink-0">
                <i class="fa-solid fa-dice" style="font-size:11px"></i> Buatkan Otomatis
              </button>
            </div>
            @error('password') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            <ul id="pwChecklist" class="text-muted mt-2 mb-0 ps-0" style="font-size:11px;list-style:none"></ul>
          </div>

          <div>
            <label class="form-label">Ulangi Password Baru</label>
            <input type="password" name="password_confirmation" id="pwConfirmField" required class="form-control">
          </div>

          <button type="submit" class="btn btn-theme" style="width:fit-content"><i class="fa-solid fa-key" style="font-size:11px"></i> Ganti Password</button>
        </form>
      </div>

      {{-- 2FA --}}
      <div class="card-public p-4">
        <div class="d-flex align-items-center gap-2 mb-1">
          <h2 class="small fw-bold text-dark mb-0">Verifikasi Dua Langkah (2FA)</h2>
          <span class="badge {{ $client->two_factor_enabled ? 'badge-soft-success' : 'badge-soft-secondary' }}">
            {{ $client->two_factor_enabled ? 'Aktif' : 'Nonaktif' }}
          </span>
        </div>
        <p class="text-muted mb-3" style="font-size:14px;line-height:1.6">
          Saat aktif, setiap login akan meminta kode 6 digit yang dikirim ke email
          <b class="text-dark">{{ $client->email }}</b>. Ini melindungi akun Anda meski password bocor.
        </p>

        <form method="POST" action="{{ route('client.profile.two-factor') }}" class="d-flex flex-column gap-2" style="max-width:20rem">
          @csrf
          @if ($client->two_factor_enabled)
            <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Password untuk menonaktifkan" required>
            @error('current_password') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-shield-halved" style="font-size:11px"></i> Nonaktifkan 2FA</button>
          @else
            <button type="submit" class="btn btn-theme btn-sm"><i class="fa-solid fa-shield-halved" style="font-size:11px"></i> Aktifkan 2FA</button>
          @endif
        </form>
      </div>

      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Aktivitas Login Terakhir</h2>
        <div class="row g-3">
          <div class="col-6">
            <p class="text-muted mb-0" style="font-size:11px">Waktu</p>
            <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $client->last_login_at?->format('d M Y H:i') ?? '—' }}</p>
          </div>
          <div class="col-6">
            <p class="text-muted mb-0" style="font-size:11px">Alamat IP</p>
            <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $client->last_login_ip ?? '—' }}</p>
          </div>
        </div>
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
        vapidKey: '{{ route('client.push.vapid-key') }}',
        subscribe: '{{ route('client.push.subscribe') }}',
        unsubscribe: '{{ route('client.push.unsubscribe') }}',
      });
    }
  });
  </script>
@endsection
