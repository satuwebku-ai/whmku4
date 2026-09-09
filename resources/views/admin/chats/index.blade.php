@extends('layouts.admin')

@section('title', 'Live Chat')

@section('content')

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">Live Chat</h1>
    <p class="small text-muted mb-0">Percakapan dari widget chat di halaman publik dan area klien.</p>
  </div>

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="{{ route('admin.chats') }}"
       class="px-3 py-2 small fw-medium text-decoration-none rounded-pill {{ request('status') !== 'closed' ? 'text-white' : 'text-muted' }}"
       style="{{ request('status') !== 'closed' ? 'background:#4f46e5' : 'background:#f1f5f9' }}">
      Aktif ({{ $counts['open'] }})
      @if ($counts['unread'] > 0)
        <span class="badge bg-danger rounded-pill ms-1">{{ $counts['unread'] }}</span>
      @endif
    </a>
    <a href="{{ route('admin.chats', ['status' => 'closed']) }}"
       class="px-3 py-2 small fw-medium text-decoration-none rounded-pill {{ request('status') === 'closed' ? 'text-white' : 'text-muted' }}"
       style="{{ request('status') === 'closed' ? 'background:#4f46e5' : 'background:#f1f5f9' }}">
      Ditutup ({{ $counts['closed'] }})
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div>
      @forelse ($conversations as $chat)
        <a href="{{ route('admin.chats.show', $chat) }}"
           class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none border-bottom" style="{{ $chat->unread_for_admin > 0 ? 'background:rgba(79,70,229,.04)' : '' }}">
          <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:40px;height:40px;font-size:12px;background:rgba(79,70,229,.12);color:#4338ca">
            {{ $chat->initials }}
          </span>

          <div class="flex-grow-1 min-w-0">
            <p class="small fw-medium text-dark text-truncate mb-0">
              {{ $chat->display_name }}
              @if ($chat->channel === 'whatsapp')
                <span class="badge" style="background:#25d366;color:#fff;font-size:9px" title="Percakapan WhatsApp"><i class="fa-brands fa-whatsapp"></i> WA</span>
              @endif
              @if ($chat->client_id)
                <span class="badge badge-soft-success ms-1">Klien</span>
              @else
                <span class="badge badge-soft-secondary ms-1">Tamu</span>
              @endif
            </p>
            <p class="text-muted text-truncate mb-0" style="font-size:12px">
              {{ \Illuminate\Support\Str::limit(optional($chat->messages()->latest('id')->first())->message ?? 'Lampiran', 70) }}
            </p>
            @if ($chat->assignedAdmin)
              <p class="text-muted mb-0 mt-1" style="font-size:10px">
                <i class="fa-solid fa-user" style="font-size:9px"></i>
                {{ $chat->assignedAdmin->id === auth('admin')->id() ? 'Anda' : $chat->assignedAdmin->name }}
              </p>
            @elseif ($chat->status === 'open')
              <p class="text-warning mb-0 mt-1" style="font-size:10px"><i class="fa-solid fa-circle-exclamation" style="font-size:9px"></i> Belum dipegang</p>
            @endif
          </div>

          <div class="text-end flex-shrink-0">
            <p class="text-muted mb-0" style="font-size:11px">{{ $chat->last_message_at?->diffForHumans() }}</p>
            @if ($chat->unread_for_admin > 0)
              <span class="badge bg-danger rounded-pill mt-1 d-inline-block" style="min-width:20px">
                {{ $chat->unread_for_admin }}
              </span>
            @endif
          </div>
        </a>
      @empty
        <div class="text-center py-5">
          <p class="text-dark small mb-1">Belum ada percakapan.</p>
          <p class="text-muted mb-0" style="font-size:12px">
            Pastikan widget sudah aktif di <a href="{{ route('admin.settings.livechat') }}" class="text-accent">Pengaturan → Live Chat</a>.
          </p>
        </div>
      @endforelse
    </div>

    @if ($conversations->hasPages())
      <div class="px-4 py-3 border-top">{{ $conversations->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
