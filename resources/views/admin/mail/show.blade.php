@extends('layouts.admin')

@section('title', $thread->subject)

@section('content')

  <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
    <div class="min-w-0">
      <a href="{{ route('admin.mail', $thread->status === 'closed' ? ['status' => 'closed'] : []) }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Email</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-1" style="word-break:break-word">
        {{ $thread->subject }}
        @if ($thread->status === 'closed')
          <span class="badge badge-soft-secondary align-middle" style="font-size:11px">Ditutup</span>
        @endif
      </h1>
      <p class="text-muted mb-0" style="font-size:12px">
        {{ $thread->display_name }} &lt;{{ $thread->contact_email }}&gt;
        @if ($thread->client)
          · <a href="{{ route('admin.clients.details', $thread->client) }}" class="text-decoration-none text-accent">Lihat profil klien</a>
        @endif
        · {{ $thread->messages->count() }} surat
      </p>
    </div>

    <div class="d-flex align-items-center gap-2">
      @if ($thread->status === 'open')
        <form method="POST" action="{{ route('admin.mail.close', $thread) }}">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Tutup</button>
        </form>
      @else
        <form method="POST" action="{{ route('admin.mail.reopen', $thread) }}">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left" style="font-size:11px"></i> Buka Kembali</button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.mail.delete', $thread) }}"
            data-confirm="Hapus email ini beserta semua surat dan lampirannya?"
            data-confirm-title="Hapus Email" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-regular fa-trash-can" style="font-size:11px"></i></button>
      </form>
    </div>
  </div>

  <div class="d-flex flex-column gap-3 mb-4">
    @foreach ($thread->messages as $message)
      @php $out = ! $message->isInbound(); @endphp
      <div class="card border rounded-4 overflow-hidden" style="{{ $out ? 'border-color:#c7d2fe !important' : '' }}">
        <div class="px-4 py-3 border-bottom d-flex align-items-start justify-content-between gap-3 flex-wrap" style="{{ $out ? 'background:#eef2ff' : 'background:#f8fafc' }}">
          <div class="min-w-0">
            <p class="small fw-semibold text-dark mb-0">
              @if ($out)
                {{ $message->admin?->name ?: 'Staf' }}
                <span class="badge bg-primary ms-1" style="font-size:10px"><i class="fa-solid fa-paper-plane"></i> Terkirim</span>
              @else
                {{ $message->from_name ?: $message->from_email }}
                <span class="badge bg-light text-secondary border ms-1" style="font-size:10px"><i class="fa-solid fa-inbox"></i> Masuk</span>
              @endif
            </p>
            <p class="text-muted mb-0" style="font-size:11px">
              Dari: {{ $message->from_email }} &nbsp;·&nbsp; Kepada: {{ $message->to_email }}
            </p>
          </div>
          <p class="text-muted mb-0 flex-shrink-0" style="font-size:11px" title="{{ $message->created_at->format('d M Y H:i:s') }}">
            {{ $message->created_at->format('d M Y, H:i') }}
          </p>
        </div>

        <div class="px-4 py-3">
          @if ($message->subject)
            <p class="small fw-semibold text-dark mb-2">{{ $message->subject }}</p>
          @endif
          <div class="small text-dark" style="white-space:pre-wrap;word-break:break-word;line-height:1.65">{{ $message->body }}</div>

          @if (! empty($message->attachments))
            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
              @foreach ($message->attachments as $i => $file)
                <a href="{{ route('admin.mail.attachment', [$message, $i]) }}" class="btn btn-outline-secondary btn-sm">
                  <i class="fa-solid fa-paperclip" style="font-size:11px"></i>
                  {{ $file['name'] }}
                  <span class="text-muted" style="font-size:10px">({{ number_format(($file['size'] ?? 0) / 1024, 0) }} KB)</span>
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    @endforeach
  </div>

  <div class="card border rounded-4">
    <div class="px-4 py-3 border-bottom">
      <p class="small fw-semibold text-dark mb-0"><i class="fa-solid fa-reply text-muted" style="font-size:11px"></i> Balas</p>
    </div>
    <form method="POST" action="{{ route('admin.mail.reply', $thread) }}" enctype="multipart/form-data" class="px-4 py-3">
      @csrf

      <div class="mb-3">
        <label class="form-label small fw-medium">Kepada</label>
        <input type="text" class="form-control form-control-sm" value="{{ $thread->display_name }} <{{ $thread->contact_email }}>" disabled>
      </div>

      <div class="mb-3">
        <label for="mailSubject" class="form-label small fw-medium">Subjek</label>
        <input type="text" id="mailSubject" name="subject" value="{{ old('subject', 'Re: ' . $thread->subject) }}" maxlength="200"
               class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="mb-3">
        <label for="mailBody" class="form-label small fw-medium">Pesan</label>
        <textarea id="mailBody" name="body" rows="8" maxlength="20000" placeholder="Tulis balasan..."
                  class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="mb-3">
        <label for="mailFiles" class="form-label small fw-medium">Lampiran <span class="text-muted fw-normal">(opsional, maks. 5 berkas @ 5 MB: JPG, PNG, WEBP, PDF, TXT, ZIP)</span></label>
        <input type="file" id="mailFiles" name="attachments[]" multiple
               class="form-control form-control-sm @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror">
        @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('attachments.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <p class="text-muted mb-0" style="font-size:11px">Balasan pelanggan akan kembali ke thread ini.</p>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Balasan</button>
      </div>
    </form>
  </div>

@endsection
