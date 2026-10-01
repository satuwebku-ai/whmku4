@extends('layouts.admin')

@section('title', 'Email')

@section('content')

  <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:#eef2ff;color:#4f46e5"><i class="fa-regular fa-envelope"></i></span>
        <h1 class="h4 fw-bold text-dark mb-0">Email</h1>
      </div>
      <p class="small text-muted mb-0">Kotak masuk mailbox support. Balas langsung dari sini, atau tulis email baru ke pelanggan.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.settings.email') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-sliders" style="font-size:11px"></i> Pengaturan Email</a>
      <a href="{{ route('admin.mail.compose') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen" style="font-size:11px"></i> Tulis Email</a>
    </div>
  </div>

  <div class="row g-2 mb-3">
    @foreach ([
      ['label' => 'Kotak masuk', 'value' => $counts['open'], 'icon' => 'fa-inbox', 'tone' => 'primary'],
      ['label' => 'Belum dibaca', 'value' => $counts['unread'], 'icon' => 'fa-envelope', 'tone' => 'warning'],
      ['label' => 'Ditutup', 'value' => $counts['closed'], 'icon' => 'fa-check-double', 'tone' => 'success'],
    ] as $stat)
      <div class="col-12 col-md-4">
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

  @php
    $pill = fn (bool $active) => 'px-3 py-2 small fw-medium text-decoration-none rounded-pill ' . ($active ? 'text-white' : 'text-muted');
    $pillBg = fn (bool $active) => $active ? 'background:#4f46e5' : 'background:#f1f5f9';
  @endphp

  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="{{ route('admin.mail') }}" class="{{ $pill(! $closed && ! $unreadOnly) }}" style="{{ $pillBg(! $closed && ! $unreadOnly) }}">Kotak Masuk ({{ $counts['open'] }})</a>
    <a href="{{ route('admin.mail', ['filter' => 'unread']) }}" class="{{ $pill($unreadOnly) }}" style="{{ $pillBg($unreadOnly) }}">
      Belum dibaca
      @if ($counts['unread'] > 0)<span class="badge bg-danger rounded-pill ms-1">{{ $counts['unread'] }}</span>@endif
    </a>
    <a href="{{ route('admin.mail', ['status' => 'closed']) }}" class="{{ $pill($closed) }}" style="{{ $pillBg($closed) }}">Ditutup ({{ $counts['closed'] }})</a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex align-items-center gap-2">
      @if ($closed) <input type="hidden" name="status" value="closed">@endif
      @if ($unreadOnly) <input type="hidden" name="filter" value="unread">@endif
      <div class="position-relative flex-grow-1" style="max-width:24rem">
        <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left:12px;top:10px;font-size:11px"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari subjek, pengirim, atau isi email..." class="form-control form-control-sm ps-4">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Cari</button>
      @if (request('search')) <a href="{{ url()->current() }}" class="btn btn-link btn-sm text-muted">Reset</a>@endif
    </form>

    <div>
      @forelse ($threads as $thread)
        @php
          $unread = $thread->unread_count > 0;
          $last = $thread->latestMessage;
          $snippet = $last ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $last->body), 90) : '';
        @endphp
        <a href="{{ route('admin.mail.show', $thread) }}"
           class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none border-bottom mail-row"
           style="{{ $unread ? 'background:rgba(79,70,229,.04)' : '' }}">
          <span class="rounded-3 d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:42px;height:42px;font-size:12px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);color:#4338ca">
            {{ $thread->initials }}
          </span>

          <div class="flex-grow-1 min-w-0">
            <p class="small text-dark text-truncate mb-0 {{ $unread ? 'fw-bold' : 'fw-medium' }}">
              {{ $thread->display_name }}
              <span class="text-muted fw-normal" style="font-size:11px">&lt;{{ $thread->contact_email }}&gt;</span>
              @if ($thread->client_id)<span class="badge badge-soft-success ms-1">Klien</span>@endif
            </p>
            <p class="small text-truncate mb-0 {{ $unread ? 'text-dark fw-semibold' : 'text-secondary' }}" style="max-width:48rem">
              {{ $thread->subject }}
              @if ($snippet !== '')<span class="text-muted fw-normal"> — {{ $snippet }}</span>@endif
            </p>
          </div>

          <div class="text-end flex-shrink-0">
            <p class="text-muted mb-0" style="font-size:11px">{{ $thread->last_message_at?->diffForHumans() }}</p>
            <div class="mt-1 d-flex justify-content-end gap-1">
              @if ($thread->messages_count > 1)
                <span class="badge bg-light text-secondary border rounded-pill" title="Jumlah surat dalam thread"><i class="fa-regular fa-comment-dots"></i> {{ $thread->messages_count }}</span>
              @endif
              @if ($unread)
                <span class="badge bg-danger rounded-pill" style="min-width:20px">{{ $thread->unread_count }}</span>
              @endif
            </div>
          </div>
        </a>
      @empty
        <div class="text-center py-5">
          <p class="text-dark small mb-1">{{ request('search') ? 'Tidak ada email yang cocok.' : ($closed ? 'Belum ada email yang ditutup.' : 'Kotak masuk kosong.') }}</p>
          <p class="text-muted mb-0" style="font-size:12px">
            Email masuk diambil dari mailbox support tiap beberapa menit. Atur di
            <a href="{{ route('admin.settings.email') }}" class="text-accent">Pengaturan → Email</a>.
          </p>
        </div>
      @endforelse
    </div>

    @if ($threads->hasPages())
      <div class="px-4 py-3 border-top">{{ $threads->links('pagination.bootstrap') }}</div>
    @endif
  </div>

  <style>
    .mail-row:hover { background: rgba(79, 70, 229, .06) !important; }
  </style>

@endsection
