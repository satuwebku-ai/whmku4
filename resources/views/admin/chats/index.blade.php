@extends('layouts.admin')

@section('title', 'Live Chat')

@section('content')

  <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:#eef2ff;color:#4f46e5"><i class="fa-solid fa-comments"></i></span>
        <h1 class="h4 fw-bold text-dark mb-0">Live Chat</h1>
      </div>
      <p class="small text-muted mb-0">Satu inbox untuk chat website, WhatsApp, dan percakapan yang siap dijadikan tiket.</p>
    </div>
    <a href="{{ route('admin.settings.livechat') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-sliders" style="font-size:11px"></i> Atur Widget</a>
  </div>

  <div class="row g-2 mb-3">
    @foreach ([
      ['label' => 'Percakapan aktif', 'value' => $counts['open'], 'icon' => 'fa-comments', 'tone' => 'primary'],
      ['label' => 'Belum dibaca', 'value' => $counts['unread'], 'icon' => 'fa-bell', 'tone' => 'warning'],
      ['label' => 'Belum ditangani', 'value' => $counts['unassigned'], 'icon' => 'fa-inbox', 'tone' => 'danger'],
      ['label' => 'Selesai', 'value' => $counts['closed'], 'icon' => 'fa-check-double', 'tone' => 'success'],
    ] as $stat)
      <div class="col-6 col-xl-3">
        <div class="card border rounded-4 px-3 py-3 h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <p class="text-muted mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:.06em">{{ $stat['label'] }}</p>
              <p class="h5 fw-bold text-dark mb-0">{{ $stat['value'] }}</p>
            </div>
            <span class="rounded-3 d-flex align-items-center justify-content-center text-{{ $stat['tone'] }}" style="width:34px;height:34px;background:rgba(var(--bs-{{ $stat['tone'] }}-rgb),.1)"><i class="fa-solid {{ $stat['icon'] }}" style="font-size:13px"></i></span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
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
    <form method="GET" class="px-4 py-3 border-bottom d-flex align-items-center gap-2">
      @if (request('status') === 'closed') <input type="hidden" name="status" value="closed">@endif
      <div class="position-relative flex-grow-1" style="max-width:24rem">
        <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left:12px;top:10px;font-size:11px"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, nomor WhatsApp..." class="form-control form-control-sm ps-4">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Cari</button>
      @if (request('search')) <a href="{{ url()->current() }}{{ request('status') === 'closed' ? '?status=closed' : '' }}" class="btn btn-link btn-sm text-muted">Reset</a>@endif
    </form>
    <div>
      @forelse ($conversations as $chat)
        <a href="{{ route('admin.chats.show', $chat) }}"
            class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none border-bottom chat-inbox-row" style="{{ $chat->unread_for_admin > 0 ? 'background:rgba(79,70,229,.04)' : '' }}">
          <span class="rounded-3 d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:42px;height:42px;font-size:12px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);color:#4338ca">
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
             <p class="text-muted text-truncate mb-0" style="font-size:12px;max-width:38rem">
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

  <style>
    .chat-inbox-row{ transition:background-color .15s ease,transform .15s ease; }
    .chat-inbox-row:hover{ background:#f8faff!important; }
  </style>

@endsection
