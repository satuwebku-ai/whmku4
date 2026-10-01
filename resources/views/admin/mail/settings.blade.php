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
        <p style="font-size:12px;color:#64748b">Template sekarang punya menu sendiri: tambah, ubah, kategori, aktif/nonaktif, dan sambungan ke AI chat.</p>
        <a href="{{ route('admin.templates.index') }}" class="ix-btn pri" style="text-decoration:none;display:inline-block"><i class="fa-solid fa-bolt"></i> Buka Template Balasan ({{ $templates->count() }})</a>
      </div>
    </section>
  </div>

@endsection
