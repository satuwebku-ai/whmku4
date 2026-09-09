@extends('layouts.admin')

@section('title', 'Tiket ' . $ticket->ticket_number)

@section('content')

  @php
    $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'overdue' => 'badge-soft-danger', 'suspended' => 'badge-soft-danger', 'inactive' => 'badge-soft-secondary'];
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <a href="{{ route('admin.tickets') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Tiket</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $ticket->subject }}</h1>
      <p class="small text-muted mb-0">{{ $ticket->ticket_number }} · dibuat {{ $ticket->created_at->format('d M Y H:i') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge {{ $badgeMap[$ticket->priority_badge] ?? 'badge-soft-secondary' }}" style="font-size:13px;padding:.4rem .8rem">{{ ucfirst($ticket->priority) }}</span>
      <span class="badge {{ $badgeMap[$ticket->status_badge] ?? 'badge-soft-secondary' }}" style="font-size:13px;padding:.4rem .8rem">{{ $ticket->status_label }}</span>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">

      {{-- Percakapan --}}
      <div class="mb-3">
        @foreach ($ticket->replies as $reply)
          @php
            $isNote = $reply->is_internal_note;
            $isStaff = $reply->isFromStaff() && ! $isNote;
            $cardStyle = $isNote ? 'border-color:#fde68a!important;background:#fffbeb'
                       : ($isStaff ? 'border-color:#c7d2fe!important;background:rgba(79,70,229,.04)' : '');
          @endphp
          <div class="card border rounded-4 p-4 mb-2" style="{{ $cardStyle }}">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                      style="width:32px;height:32px;font-size:11px;{{ $reply->isFromStaff() ? 'background:rgba(79,70,229,.12);color:#4338ca' : 'background:#e2e8f0;color:#475569' }}">
                  {{ strtoupper(substr($reply->author_name, 0, 2)) }}
                </span>
                <div>
                  <p class="small fw-bold text-dark mb-0">
                    {{ $reply->author_name }}
                    <span class="text-muted fw-normal" style="font-size:11px">{{ $reply->isFromStaff() ? '(Staf)' : '(Klien)' }}</span>
                  </p>
                  <p class="text-muted mb-0" style="font-size:11px">{{ $reply->created_at->format('d M Y H:i') }}</p>
                </div>
              </div>
              @if ($isNote)
                <span class="badge badge-soft-warning"><i class="fa-solid fa-lock" style="font-size:9px"></i> Catatan Internal</span>
              @endif
            </div>

            <div class="small text-dark" style="white-space:pre-line;line-height:1.6">{{ $reply->message }}</div>

            @if ($reply->attachments->isNotEmpty())
              <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach ($reply->attachments as $attachment)
                  <a href="{{ $attachment->url }}" target="_blank"
                     class="d-inline-flex align-items-center gap-2 text-decoration-none text-accent px-2 py-1 rounded-3"
                     style="font-size:12px;background:#f1f5f9">
                    <i class="fa-solid fa-paperclip"></i> {{ $attachment->original_name }}
                    @if ($attachment->size_label)<span class="text-muted">({{ $attachment->size_label }})</span>@endif
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        @endforeach
      </div>

      {{-- Form balasan --}}
      @if (! $ticket->isClosed())
        <div class="card border rounded-4 p-4">
          <h2 class="small fw-bold text-dark mb-3">Balas Tiket</h2>
          <form method="POST" action="{{ route('admin.ticket.reply') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">

            <textarea name="message" rows="5" class="form-control form-control-sm mb-2" placeholder="Tulis balasan untuk klien..." required>{{ old('message') }}</textarea>
            @error('message') <p class="text-danger mb-2" style="font-size:12px">{{ $message }}</p> @enderror

            <div class="mb-2">
              <label class="form-label small fw-medium text-dark">Lampiran (opsional, maks 5 berkas @ 5MB)</label>
              <input type="file" name="attachments[]" multiple class="form-control form-control-sm">
              @error('attachments') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              @error('attachments.*') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            </div>

            <label class="d-flex align-items-center gap-2 small text-dark mb-3">
              <input type="checkbox" name="is_internal_note" value="1" class="form-check-input" style="margin-top:0">
              Simpan sebagai catatan internal (tidak terlihat klien, status tiket tidak berubah)
            </label>

            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Balasan</button>
          </form>
        </div>
      @else
        <div class="card border rounded-4 p-4 text-center text-muted small">
          Tiket ini sudah ditutup {{ $ticket->closed_at?->diffForHumans() }}. Buka kembali untuk membalas.
        </div>
      @endif
    </div>

    {{-- Sidebar aksi --}}
    <div class="col-12 col-lg-4">
      @if ($ticket->domain && str_starts_with($ticket->subject, 'Permintaan Kode Transfer'))
        <div class="card border rounded-4 p-4 mb-3" style="background:#fffbeb;border-color:#fde68a!important">
          <h2 class="small fw-bold mb-1" style="color:#92400e">
            <i class="fa-solid fa-key"></i> Permintaan Kode Transfer
          </h2>
          <p class="mb-3" style="font-size:12px;color:#b45309">
            Domain: <a href="{{ route('admin.domains.details', $ticket->domain) }}" class="text-decoration-underline" style="color:inherit">{{ $ticket->domain->domain_name }}</a>
          </p>

          @if (session('preview_transfer_code'))
            <div class="rounded-3 px-3 py-2 small mb-3" style="background:#1e293b;color:#fff">
              <p class="mb-1" style="font-size:11px;color:#cbd5e1">Kode transfer (pratinjau — belum terkirim ke klien):</p>
              <p class="fw-bold mb-0" style="font-family:monospace;letter-spacing:.05em">{{ session('preview_transfer_code') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.tickets.approve-transfer-code', $ticket) }}"
                  data-confirm="Kirim kode ini ke email klien sekarang?" data-confirm-title="Setujui & Kirim" data-confirm-style="warn" data-confirm-label="Ya, Kirim ke Klien">
              @csrf
              <input type="hidden" name="code" value="{{ session('preview_transfer_code') }}">
              <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Setujui &amp; Kirim ke Email Klien
              </button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.tickets.preview-transfer-code', $ticket) }}">
              @csrf
              <button type="submit" class="btn btn-outline-secondary btn-sm w-100" style="border-color:#fcd34d;color:#b45309">
                <i class="fa-solid fa-eye" style="font-size:11px"></i> Lihat Kode Dulu
              </button>
            </form>
          @endif
        </div>
      @endif

      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-3">Informasi</h2>
        <div class="mb-2">
          <p class="text-muted mb-1" style="font-size:11px">KLIEN</p>
          <p class="fw-medium text-dark mb-0">
            @if ($ticket->client)
              <a href="{{ route('admin.clients.details', $ticket->client) }}" class="text-decoration-none text-accent">{{ $ticket->client->name }}</a>
            @else
              —
            @endif
          </p>
        </div>
        <div class="mb-2">
          <p class="text-muted mb-1" style="font-size:11px">DEPARTEMEN</p>
          <p class="fw-medium text-dark text-capitalize mb-0">{{ $ticket->department }}</p>
        </div>
        <div>
          <p class="text-muted mb-1" style="font-size:11px">BALASAN TERAKHIR</p>
          <p class="fw-medium text-dark mb-0">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</p>
        </div>
      </div>

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Kelola</h2>

        <form method="POST" action="{{ route('admin.ticket.assign') }}" class="mb-3">
          @csrf
          <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
          <label class="form-label small fw-medium text-dark">Tugaskan ke</label>
          <div class="d-flex gap-2">
            <select name="assigned_to" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
              <option value="">— Belum ditugaskan —</option>
              @foreach ($admins as $admin)
                <option value="{{ $admin->id }}" @selected($ticket->assigned_to == $admin->id)>{{ $admin->name }}</option>
              @endforeach
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm flex-shrink-0"><i class="fa-solid fa-check" style="font-size:11px"></i></button>
          </div>
        </form>

        <form method="POST" action="{{ route('admin.ticket.priority') }}" class="mb-3">
          @csrf
          <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
          <label class="form-label small fw-medium text-dark">Prioritas</label>
          <div class="d-flex gap-2">
            <select name="priority" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
              <option value="low" @selected($ticket->priority === 'low')>Low</option>
              <option value="medium" @selected($ticket->priority === 'medium')>Medium</option>
              <option value="high" @selected($ticket->priority === 'high')>High</option>
              <option value="urgent" @selected($ticket->priority === 'urgent')>Urgent</option>
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm flex-shrink-0"><i class="fa-solid fa-check" style="font-size:11px"></i></button>
          </div>
        </form>

        <div class="pt-2 border-top">
          @if ($ticket->isClosed())
            <form method="POST" action="{{ route('admin.ticket.reopen') }}">
              @csrf
              <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
              <button type="submit" class="btn btn-primary btn-sm w-100 text-start"><i class="fa-solid fa-rotate-left" style="font-size:11px"></i> Buka Kembali</button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.ticket.close') }}" data-confirm="Tutup tiket ini?" data-confirm-title="Tutup Tiket" data-confirm-style="warn" data-confirm-label="Ya, Tutup">
              @csrf
              <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
              <button type="submit" class="btn btn-outline-danger btn-sm w-100 text-start"><i class="fa-solid fa-check-double" style="font-size:11px"></i> Tutup Tiket</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>

@endsection
