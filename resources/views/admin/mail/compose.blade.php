@extends('layouts.admin')

@section('title', 'Tulis Email')

@section('content')

  <div class="ix-app">
    @include('admin.mail._sidebar', ['folder' => 'compose'])

    <section class="ix-main">
      <div class="ix-head">
        <h1><i class="fa-regular fa-pen-to-square" style="color:#4f46e5"></i> Pesan baru</h1>
      </div>

      <form method="POST" action="{{ route('admin.mail.store') }}" enctype="multipart/form-data" class="ix-form">
        @csrf

        <div class="ix-field">
          <label for="toEmail">Kepada</label>
          <div>
            <input type="email" id="toEmail" name="to_email" value="{{ old('to_email', $to) }}" maxlength="255" placeholder="pelanggan@contoh.com" required>
            @error('to_email')<div class="ix-err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="ix-field">
          <label for="toName">Nama</label>
          <div>
            <input type="text" id="toName" name="to_name" value="{{ old('to_name') }}" maxlength="120" placeholder="Opsional">
            @error('to_name')<div class="ix-err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="ix-field">
          <label for="mailSubject">Subjek</label>
          <div>
            <input type="text" id="mailSubject" name="subject" value="{{ old('subject') }}" maxlength="200" required>
            @error('subject')<div class="ix-err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="ix-editor">
          <div class="ix-editor-bar">
            <label title="Lampirkan berkas"><i class="fa-solid fa-paperclip"></i> Lampirkan
              <input type="file" id="mailFiles" name="attachments[]" multiple class="d-none">
            </label>
            @include('admin.mail._tpl-select')
            <span style="margin-left:auto">maks. 5 berkas @ 5 MB · JPG, PNG, WEBP, PDF, TXT, ZIP</span>
          </div>
          <textarea id="mailBody" name="body" maxlength="20000" required>{{ old('body') }}</textarea>
          <div class="ix-files" id="mailFileList"></div>
          <div class="ix-editor-foot"><span id="mailLines">baris: 1</span><span id="mailWords">kata: 0</span></div>
        </div>
        @error('body')<div class="ix-err mb-2">{{ $message }}</div>@enderror
        @error('attachments')<div class="ix-err mb-2">{{ $message }}</div>@enderror
        @error('attachments.*')<div class="ix-err mb-2">{{ $message }}</div>@enderror

        <div class="ix-actions">
          <button type="submit" class="ix-btn pri">Kirim</button>
          <a href="{{ route('admin.mail') }}" class="ix-btn sec">Batal</a>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin:.8rem 0 0">Dikirim dari alamat pengirim di Pengaturan → Email. Balasan pelanggan masuk ke Kotak Masuk sebagai thread yang sama.</p>
      </form>
    </section>
  </div>

  @include('admin.mail._editor-js', ['ctx' => []])

@endsection
