@extends('client.layout')
@section('title', $ticket->ticket_number)

@section('content')
  @php
    $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'inactive' => 'badge-soft-secondary'];
  @endphp

  <a href="{{ route('client.tickets') }}" class="text-decoration-none text-muted" style="font-size:12px">&larr; Kembali ke Tiket</a>

  <div class="d-flex align-items-center justify-content-between mt-2 mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-0">{{ $ticket->subject }}</h1>
      <p class="text-muted mb-0">{{ $ticket->ticket_number }} · dibuat {{ $ticket->created_at->format('d M Y H:i') }}</p>
    </div>
    <span class="badge {{ $badgeMap[$ticket->status_badge] ?? 'badge-soft-secondary' }}">{{ $ticket->status_label }}</span>
  </div>

  {{-- Percakapan --}}
  <div class="d-flex flex-column gap-3 mb-4">
    @foreach ($ticket->publicReplies as $reply)
      <div class="card-public p-4" style="{{ $reply->isFromStaff() ? 'border-color:#c7d2fe!important;background:rgba(79,70,229,.04)' : '' }}">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                style="width:32px;height:32px;font-size:11px;{{ $reply->isFromStaff() ? 'background:rgba(79,70,229,.12);color:#4f46e5' : 'background:#e2e8f0;color:#475569' }}">
            {{ strtoupper(substr($reply->author_name, 0, 2)) }}
          </span>
          <div>
            <p class="fw-semibold text-dark mb-0" style="font-size:14px">
              {{ $reply->isFromStaff() ? $reply->author_name . ' (Tim Support)' : 'Anda' }}
            </p>
            <p class="text-muted mb-0" style="font-size:11px">{{ $reply->created_at->format('d M Y H:i') }}</p>
          </div>
        </div>

        <div class="text-muted" style="font-size:14px;white-space:pre-line;line-height:1.7">{{ $reply->message }}</div>

        @if ($reply->attachments->isNotEmpty())
          <div class="d-flex flex-wrap gap-2 mt-3">
            @foreach ($reply->attachments as $attachment)
              <a href="{{ $attachment->url }}" target="_blank"
                 class="d-inline-flex align-items-center gap-2 text-decoration-none text-theme px-2 py-1 rounded-3"
                 style="font-size:12px;background:rgba(0,0,0,.04)">
                <i class="fa-solid fa-paperclip"></i> {{ $attachment->original_name }}
                @if ($attachment->size_label)<span class="text-muted">({{ $attachment->size_label }})</span>@endif
              </a>
            @endforeach
          </div>
        @endif
      </div>
    @endforeach
  </div>

  {{-- Balas --}}
  @if ($ticket->isClosed())
    <div class="card-public p-4 text-center">
      <p class="text-muted mb-3" style="font-size:14px">
        Tiket ini sudah ditutup {{ $ticket->closed_at?->diffForHumans() }}.
      </p>
      <a href="{{ route('client.tickets.create') }}" class="btn btn-theme">Buat Tiket Baru</a>
    </div>
  @else
    <div class="card-public p-4">
      <h2 class="small fw-bold text-dark mb-3">Balas</h2>
      <form method="POST" action="{{ route('client.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
        @csrf
        <textarea name="message" rows="5" required class="form-control" placeholder="Tulis balasan Anda...">{{ old('message') }}</textarea>
        @error('message') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror

        <div>
          <label class="form-label">Lampiran <span class="text-muted fw-normal">(opsional, maks 5 berkas @ 5MB)</span></label>
          <input type="file" name="attachments[]" multiple class="form-control form-control-sm">
          @error('attachments') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          @error('attachments.*') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>

        <div class="d-flex align-items-center gap-3">
          <button type="submit" class="btn btn-theme"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Balasan</button>
        </div>
      </form>

      <form method="POST" action="{{ route('client.tickets.close', $ticket) }}" class="mt-4 pt-4 border-top"
            data-confirm="Tutup tiket ini? Anda tetap bisa membuat tiket baru nanti." data-confirm-title="Tutup Tiket" data-confirm-style="warn" data-confirm-label="Ya, Tutup">
        @csrf
        <button type="submit" class="btn btn-link p-0 text-muted" style="font-size:12px;text-decoration:none">
          <i class="fa-solid fa-check-double"></i> Masalah sudah selesai — tutup tiket ini
        </button>
      </form>
    </div>
  @endif
@endsection
