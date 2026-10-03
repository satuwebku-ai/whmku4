@extends('layouts.admin')

@section('title', $thread->subject)

@section('content')

  @php
    $folder = $thread->status === 'closed' ? 'closed' : 'inbox';
    $fmtSize = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 2) . ' MB' : number_format(max($b, 1) / 1024, 0) . ' KB';
  @endphp

  <div class="ix-app">
    @include('admin.mail._sidebar', ['folder' => $folder])

    <section class="ix-main">
      <div class="ix-head">
        <h1>
          <a href="{{ route('admin.mail', $thread->status === 'closed' ? ['status' => 'closed'] : []) }}" class="ix-icon-btn" title="Kembali" aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></a>
          {{ $thread->subject }}
          @if ($thread->status === 'closed')<span class="ix-pill mute">Ditutup</span>@endif
        </h1>

        <div class="ix-actions">
          @if ($thread->client)
            <a href="{{ route('admin.clients.details', $thread->client) }}" class="ix-icon-btn" title="Profil klien"><i class="fa-regular fa-user"></i></a>
          @endif
          @if ($thread->status === 'open')
            <form method="POST" action="{{ route('admin.mail.close', $thread) }}">@csrf
              <button type="submit" class="ix-icon-btn" title="Tutup / arsipkan"><i class="fa-solid fa-box-archive"></i></button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.mail.reopen', $thread) }}">@csrf
              <button type="submit" class="ix-icon-btn" title="Buka kembali"><i class="fa-solid fa-rotate-left"></i></button>
            </form>
          @endif
          <form method="POST" action="{{ route('admin.mail.delete', $thread) }}"
                data-confirm="Hapus email ini beserta semua surat dan lampirannya?"
                data-confirm-title="Hapus Email" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
            @csrf @method('DELETE')
            <button type="submit" class="ix-icon-btn danger" title="Hapus"><i class="fa-regular fa-trash-can"></i></button>
          </form>
        </div>
      </div>

      <div class="ix-msgs">
        @foreach ($thread->messages as $message)
          @php
            $out = ! $message->isInbound();
            $name = $out ? ($message->is_auto ? 'Balasan Otomatis' : ($message->admin?->name ?: 'Staf')) : ($message->from_name ?: $message->from_email);
            $files = $message->attachments ?? [];
            $total = collect($files)->sum('size');
            $ini = strtoupper(mb_substr($name, 0, 1) . (preg_match('/\s(\S)/u', $name, $m) ? $m[1] : ''));
          @endphp
          <article class="ix-mail {{ $out ? 'ix-out' : '' }}">
            <div class="ix-mail-h">
              <span class="ix-av">{{ $ini }}</span>
              <div style="min-width:0">
                <span class="nm">{{ $name }}</span>
                @if ($message->is_auto)<span class="ix-pill"><i class="fa-solid fa-robot"></i> Robot</span>@endif
                <span class="to">&nbsp;kepada {{ $out ? $message->to_email : 'saya' }}</span>
                <div class="to">{{ $message->from_email }}</div>
              </div>
              <span class="dt" title="{{ $message->created_at->format('d M Y H:i:s') }}">{{ $message->created_at->translatedFormat('d M Y, H:i') }}</span>
            </div>

            @if ($message->subject)<div class="ix-mail-s">{{ $message->subject }}</div>@endif
            <div class="ix-mail-b">{{ $message->body }}</div>

            @if (! empty($files))
              <div class="ix-att">
                <h4>Lampiran ({{ count($files) }} berkas, {{ $fmtSize($total) }})</h4>
                @foreach ($files as $i => $file)
                  <a href="{{ route('admin.mail.attachment', [$message, $i]) }}">
                    <i class="fa-regular fa-file"></i> {{ $file['name'] }} <small>({{ $fmtSize($file['size'] ?? 0) }})</small>
                  </a>
                @endforeach
              </div>
            @endif
          </article>
        @endforeach
      </div>

      <form method="POST" action="{{ route('admin.mail.reply', $thread) }}" enctype="multipart/form-data" class="ix-form">
        @csrf
        <p style="font-size:13px;font-weight:600;color:#0f172a;margin-bottom:.8rem"><i class="fa-solid fa-reply" style="color:#94a3b8;font-size:11px"></i> Balas {{ $thread->display_name }}</p>

        <div class="ix-field">
          <label for="mailSubject">Subjek</label>
          <div>
            <input type="text" id="mailSubject" name="subject" value="{{ old('subject', 'Re: ' . $thread->subject) }}" maxlength="200" required>
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
          <textarea id="mailBody" name="body" maxlength="20000" placeholder="Tulis balasan..." required>{{ old('body') }}</textarea>
          <div class="ix-files" id="mailFileList"></div>
          <div class="ix-editor-foot"><span id="mailLines">baris: 1</span><span id="mailWords">kata: 0</span></div>
        </div>
        @error('body')<div class="ix-err mb-2">{{ $message }}</div>@enderror
        @error('attachments')<div class="ix-err mb-2">{{ $message }}</div>@enderror
        @error('attachments.*')<div class="ix-err mb-2">{{ $message }}</div>@enderror

        <div class="ix-actions">
          <button type="submit" class="ix-btn pri"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Balasan</button>
          <span style="font-size:11px;color:#94a3b8">Balasan pelanggan akan kembali ke thread ini.</span>
        </div>
      </form>
    </section>
  </div>

  @include('admin.mail._editor-js', ['ctx' => ['nama' => $thread->display_name, 'email' => $thread->contact_email]])

@endsection
