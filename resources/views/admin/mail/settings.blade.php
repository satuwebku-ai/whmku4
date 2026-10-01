@extends('layouts.admin')

@section('title', 'Otomatisasi Email')

@section('content')

  <div class="ix-app">
    @include('admin.mail._sidebar', ['folder' => 'settings'])

    <section class="ix-main">
      <div class="ix-head">
        <h1><i class="fa-solid fa-robot" style="color:#4f46e5"></i> Otomatisasi &amp; Template</h1>
      </div>

      <form method="POST" action="{{ route('admin.mail.settings.update') }}" class="ix-form" style="border-bottom:1px solid #e2e8f0">
        @csrf

        <h2 style="font-size:14px;font-weight:700;color:#0f172a">Balasan otomatis (robot)</h2>
        <p style="font-size:12px;color:#64748b">Dikirim sekali saat email pertama dari pelanggan masuk, sebagai tanda terima. Robot tidak membalas lagi sampai admin membalas sendiri, dan tidak membalas email otomatis/bounce.</p>

        <label class="d-flex align-items-center gap-2 mb-3" style="font-size:13px">
          <input type="checkbox" class="ix-chk" name="mail_autoreply_enabled" value="1" @checked($v['mail_autoreply_enabled'] === '1')> Aktifkan balasan otomatis
        </label>
        <textarea name="mail_autoreply_body" rows="8" class="form-control mb-1" style="font-size:13px" required>{{ old('mail_autoreply_body', $v['mail_autoreply_body']) }}</textarea>
        @error('mail_autoreply_body')<div class="ix-err">{{ $message }}</div>@enderror
        <p style="font-size:11px;color:#94a3b8">Penanda: <code>{nama}</code> <code>{site}</code> <code>{ref}</code> (nomor referensi) <code>{jam_kerja}</code></p>

        <h2 style="font-size:14px;font-weight:700;color:#0f172a;margin-top:1.5rem">Tutup otomatis jika tidak ada balasan</h2>
        <p style="font-size:12px;color:#64748b">Hanya thread yang pesan terakhirnya balasan admin yang ditutup. Thread yang menunggu balasan admin tidak pernah ditutup otomatis. Kalau pelanggan membalas lagi, thread terbuka kembali sendiri.</p>

        <label class="d-flex align-items-center gap-2 mb-2" style="font-size:13px">
          <input type="checkbox" class="ix-chk" name="mail_autoclose_enabled" value="1" @checked($v['mail_autoclose_enabled'] === '1')> Aktifkan tutup otomatis
        </label>
        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:13px">
          Tutup setelah
          <input type="number" name="mail_autoclose_hours" min="1" max="720" value="{{ old('mail_autoclose_hours', $v['mail_autoclose_hours']) }}" class="form-control form-control-sm" style="width:90px">
          jam tanpa balasan pelanggan
        </div>
        @error('mail_autoclose_hours')<div class="ix-err">{{ $message }}</div>@enderror
        <label class="d-flex align-items-center gap-2 mb-2" style="font-size:13px">
          <input type="checkbox" class="ix-chk" name="mail_autoclose_notice" value="1" @checked($v['mail_autoclose_notice'] === '1')> Kirim email pemberitahuan ke pelanggan saat ditutup
        </label>
        <textarea name="mail_autoclose_body" rows="7" class="form-control mb-1" style="font-size:13px" required>{{ old('mail_autoclose_body', $v['mail_autoclose_body']) }}</textarea>
        @error('mail_autoclose_body')<div class="ix-err">{{ $message }}</div>@enderror
        <p style="font-size:11px;color:#94a3b8">Penanda: <code>{nama}</code> <code>{site}</code> <code>{jam}</code>. Dijalankan oleh cron <b>Tutup Email Tanpa Balasan</b> (Pengaturan → Cron Jobs).</p>

        <button type="submit" class="ix-btn pri mt-2">Simpan</button>
      </form>

      <div class="ix-form">
        <h2 style="font-size:14px;font-weight:700;color:#0f172a">Template balasan (teks support)</h2>
        <p style="font-size:12px;color:#64748b">Muncul sebagai pilihan “⚡ Template…” saat membalas email dan live chat. Penanda: <code>{nama}</code> <code>{email}</code> <code>{site}</code> <code>{admin}</code></p>

        @foreach ($templates as $t)
          <details class="mb-2" style="border:1px solid #e2e8f0;border-radius:.6rem">
            <summary style="padding:.6rem .9rem;font-size:13px;cursor:pointer;font-weight:600;color:#1e293b">{{ $t->title }}</summary>
            <div style="padding:.2rem .9rem .9rem">
              <form method="POST" action="{{ route('admin.mail.templates.update', $t) }}">
                @csrf
                <input type="text" name="title" value="{{ $t->title }}" maxlength="120" class="form-control form-control-sm mb-2" placeholder="Judul" required>
                <input type="text" name="subject" value="{{ $t->subject }}" maxlength="200" class="form-control form-control-sm mb-2" placeholder="Subjek (opsional, hanya untuk email)">
                <textarea name="body" rows="6" class="form-control mb-2" style="font-size:13px" required>{{ $t->body }}</textarea>
                <div class="ix-actions">
                  <button type="submit" class="ix-btn pri" style="padding:.4rem 1rem">Simpan</button>
                  <button type="submit" form="delTpl{{ $t->id }}" class="ix-btn sec" style="padding:.4rem 1rem;color:#e11d48">Hapus</button>
                </div>
              </form>
              <form id="delTpl{{ $t->id }}" method="POST" action="{{ route('admin.mail.templates.delete', $t) }}"
                    data-confirm="Hapus template “{{ $t->title }}”?" data-confirm-title="Hapus Template" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                @csrf @method('DELETE')
              </form>
            </div>
          </details>
        @endforeach

        <details style="border:1px dashed #c7d2fe;border-radius:.6rem" {{ $errors->has('title') || $errors->has('body') ? 'open' : '' }}>
          <summary style="padding:.6rem .9rem;font-size:13px;cursor:pointer;color:#4f46e5;font-weight:600"><i class="fa-solid fa-plus"></i> Tambah template</summary>
          <form method="POST" action="{{ route('admin.mail.templates.store') }}" style="padding:.2rem .9rem .9rem">
            @csrf
            <input type="text" name="title" value="{{ old('title') }}" maxlength="120" class="form-control form-control-sm mb-2" placeholder="Judul, mis. Cara reset password" required>
            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="200" class="form-control form-control-sm mb-2" placeholder="Subjek (opsional)">
            <textarea name="body" rows="6" class="form-control mb-2" style="font-size:13px" placeholder="Isi template…" required>{{ old('body') }}</textarea>
            @error('title')<div class="ix-err">{{ $message }}</div>@enderror
            @error('body')<div class="ix-err">{{ $message }}</div>@enderror
            <button type="submit" class="ix-btn pri" style="padding:.4rem 1rem">Tambah</button>
          </form>
        </details>
      </div>
    </section>
  </div>

@endsection
