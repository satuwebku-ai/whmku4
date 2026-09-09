@extends('layouts.admin')

@section('title', $admin->exists ? 'Edit Admin' : 'Tambah Admin')

@section('content')

  <div class="mb-4">
    <a href="{{ route('admin.admins') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Manajemen Admin</a>
    <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $admin->exists ? 'Edit Admin' : 'Tambah Admin' }}</h1>
  </div>

  <form method="POST" action="{{ $admin->exists ? route('admin.admin.update', $admin) : route('admin.admin.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama Lengkap</label>
        <input type="text" name="name" value="{{ old('name', $admin->name) }}" class="form-control form-control-sm" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Username</label>
        <input type="text" name="username" value="{{ old('username', $admin->username) }}" class="form-control form-control-sm" required>
        @error('username') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Dipakai untuk login. Huruf, angka, dan tanda hubung.</p>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Email</label>
      <input type="email" name="email" value="{{ old('email', $admin->email) }}" class="form-control form-control-sm" required>
      @error('email') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Tujuan kode OTP dan notifikasi admin.</p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Peran</label>
      <select name="role" id="roleSelect" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
        @foreach (\App\Models\Admin::ROLES as $key => $desc)
          <option value="{{ $key }}" @selected(old('role', $admin->role ?? 'admin') === $key)>{{ $desc }}</option>
        @endforeach
      </select>
      @error('role') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div id="modulesSection" class="mb-3 pt-3 border-top">
      <label class="form-label small fw-medium text-dark mb-1">Modul yang Boleh Diakses</label>
      <p class="text-muted mb-2" style="font-size:11px">
        Superadmin bisa atur manual siapa saja yang boleh masuk ke modul apa. Memilih peran di atas cuma
        mengisi centang bawaan — bebas diubah sendiri per akun. Kalau tidak ada yang dicentang, akun ini
        terkunci total dari semua modul (tetap bisa login &amp; lihat profil sendiri).
      </p>
      <input type="hidden" name="permissions_submitted" value="1">
      <div class="row g-2">
        @php
          $currentModules = old('permissions', $admin->exists ? $admin->effectiveModules() : (\App\Models\Admin::ROLE_DEFAULT_MODULES['admin'] ?? []));
        @endphp
        @foreach (\App\Models\Admin::MODULES as $key => $label)
          <div class="col-sm-6">
            <label class="d-flex align-items-start gap-2 small text-dark border rounded-3 px-2 py-2" style="cursor:pointer">
              <input type="checkbox" name="permissions[]" value="{{ $key }}" class="form-check-input module-checkbox" style="margin-top:2px"
                     @checked(in_array($key, $currentModules, true))>
              <span>{{ $label }}</span>
            </label>
          </div>
        @endforeach
      </div>
      @error('permissions') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div id="superadminNote" class="mb-3 pt-3 border-top d-none">
      <div class="rounded-3 border px-3 py-2" style="font-size:12px;background:#f8fafc;color:#64748b">
        <i class="fa-solid fa-circle-info"></i> Superadmin selalu punya akses ke semua modul — centang di atas diabaikan untuk peran ini.
      </div>
    </div>

    <div class="row g-3 mb-3 pt-3 border-top">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Password {{ $admin->exists ? '(kosongkan jika tidak diganti)' : '' }}</label>
        <div class="d-flex gap-2">
          <input type="password" name="password" id="pwField" class="form-control form-control-sm" {{ $admin->exists ? '' : 'required' }} autocomplete="new-password">
          <button type="button" onclick="lumoraGeneratePassword('pwField', 'pwConfirmField', 'pwChecklist')" class="btn btn-outline-secondary btn-sm text-nowrap flex-shrink-0">
            <i class="fa-solid fa-dice" style="font-size:11px"></i> Buatkan Otomatis
          </button>
        </div>
        @error('password') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <ul id="pwChecklist" class="text-muted mt-2 mb-0 ps-0" style="font-size:11px;list-style:none"></ul>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Ulangi Password</label>
        <input type="password" name="password_confirmation" id="pwConfirmField" class="form-control form-control-sm" {{ $admin->exists ? '' : 'required' }} autocomplete="new-password">
      </div>
    </div>

    @if (! $admin->exists || $admin->id !== auth('admin')->id())
      <label class="d-flex align-items-center gap-2 small text-dark mb-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $admin->is_active ?? true)) class="form-check-input" style="margin-top:0">
        Akun aktif (bisa login)
      </label>
    @else
      <div class="rounded-3 border px-3 py-2 mb-3" style="font-size:12px;background:#f8fafc;color:#64748b">
        <i class="fa-solid fa-circle-info"></i>
        Status akun sendiri tidak bisa diubah dari sini — supaya Anda tidak mengunci diri sendiri.
      </div>
    @endif

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.admins') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

  <script>
    /**
     * Dipakai bersama di beberapa form (Admin & Akses, Profil, Hosting
     * Account) -- satu-satunya tempat admin BENAR-BENAR membuat password
     * baru (bukan menempel kredensial API pihak ketiga).
     */
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

      // ── Checklist modul: isi ulang centang bawaan saat peran diganti,
      // dan sembunyikan checklist untuk superadmin (selalu akses penuh).
      const roleDefaults = @json(\App\Models\Admin::ROLE_DEFAULT_MODULES);
      const roleSelect = document.getElementById('roleSelect');
      const modulesSection = document.getElementById('modulesSection');
      const superadminNote = document.getElementById('superadminNote');
      const isEditingExisting = {{ $admin->exists ? 'true' : 'false' }};

      function applyRoleDefaults() {
        const role = roleSelect.value;

        if (role === 'superadmin') {
          modulesSection.classList.add('d-none');
          superadminNote.classList.remove('d-none');
          return;
        }

        modulesSection.classList.remove('d-none');
        superadminNote.classList.add('d-none');
      }

      if (roleSelect) {
        applyRoleDefaults();

        roleSelect.addEventListener('change', () => {
          applyRoleDefaults();

          // Cuma auto-isi ulang centang kalau ini form TAMBAH admin baru
          // (belum ada data tersimpan) -- di form EDIT, jangan timpa
          // pilihan manual superadmin cuma karena ganti dropdown peran.
          if (isEditingExisting) return;

          const defaults = roleDefaults[roleSelect.value] || [];
          document.querySelectorAll('.module-checkbox').forEach((cb) => {
            cb.checked = defaults.includes(cb.value);
          });
        });
      }
    });
  </script>

@endsection
