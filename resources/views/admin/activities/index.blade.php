@extends('layouts.admin')

@section('title', 'Aktivitas')

@section('content')

  @include('admin.activities._nav')

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Aktivitas Aplikasi</h1>
      <p class="small text-muted mb-0">Catatan kejadian penting: pesanan, pembayaran, tiket, dan pendaftaran klien.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      @if ($counts['unread'] > 0)
        <form method="POST" action="{{ route('admin.activities.read-all') }}">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-check-double" style="font-size:11px"></i> Tandai Semua Dibaca</button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.activities.clear-old') }}"
            data-confirm="Hapus catatan yang sudah dibaca dan berumur lebih dari 30 hari?"
            data-confirm-title="Bersihkan Catatan Lama" data-confirm-style="warn" data-confirm-label="Ya, Bersihkan">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-regular fa-trash-can" style="font-size:11px"></i> Bersihkan Lama</button>
      </form>
    </div>
  </div>

  <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="{{ route('admin.activities') }}"
       class="px-3 py-2 small fw-medium text-decoration-none rounded-pill {{ ! request('type') && ! request('unread') ? 'text-white' : 'text-muted' }}"
       style="{{ ! request('type') && ! request('unread') ? 'background:#4f46e5' : 'background:#f1f5f9' }}">
      Semua ({{ $counts['all'] }})
    </a>
    <a href="{{ route('admin.activities', ['unread' => 1]) }}"
       class="px-3 py-2 small fw-medium text-decoration-none rounded-pill {{ request('unread') ? 'text-white' : 'text-muted' }}"
       style="{{ request('unread') ? 'background:#4f46e5' : 'background:#f1f5f9' }}">
      Belum Dibaca ({{ $counts['unread'] }})
    </a>
    @foreach (['order' => 'Order', 'payment' => 'Pembayaran', 'ticket' => 'Tiket', 'client' => 'Klien', 'invoice' => 'Invoice'] as $t => $label)
      <a href="{{ route('admin.activities', ['type' => $t]) }}"
         class="px-3 py-2 small fw-medium text-decoration-none rounded-pill {{ request('type') === $t ? 'text-white' : 'text-muted' }}"
         style="{{ request('type') === $t ? 'background:#4f46e5' : 'background:#f1f5f9' }}">
        {{ $label }}
      </a>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div>
      @php
        $levelStyle = fn ($level) => match ($level) {
            'success' => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857'],
            'warning' => ['bg' => 'rgba(245,158,11,.16)', 'fg' => '#b45309'],
            'danger' => ['bg' => 'rgba(244,63,94,.14)', 'fg' => '#e11d48'],
            default => ['bg' => 'rgba(79,70,229,.12)', 'fg' => '#4338ca'],
        };
      @endphp
      @forelse ($activities as $activity)
        @php $style = $levelStyle($activity->level); @endphp
        <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom" style="{{ $activity->read_at ? '' : 'background:rgba(79,70,229,.04)' }}">
          <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:{{ $style['bg'] }};color:{{ $style['fg'] }}">
            <i class="fa-solid {{ $activity->icon }}" style="font-size:14px"></i>
          </span>

          <div class="flex-grow-1 min-w-0">
            <p class="small fw-medium text-dark mb-0">
              {{ $activity->title }}
              @unless ($activity->read_at)
                <span class="rounded-circle bg-primary d-inline-block ms-1" style="width:8px;height:8px;vertical-align:middle"></span>
              @endunless
            </p>
            @if ($activity->description)
              <p class="text-muted mb-0 mt-1" style="font-size:12px">{{ $activity->description }}</p>
            @endif
            <p class="text-muted mb-0 mt-1" style="font-size:11px">{{ $activity->created_at->diffForHumans() }}</p>
          </div>

          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            @if ($activity->link)
              <a href="{{ $activity->link }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Buka">
                <i class="fa-solid fa-arrow-right" style="font-size:12px"></i>
              </a>
            @endif
            <form method="POST" action="{{ route('admin.activity.delete', $activity) }}">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
              </button>
            </form>
          </div>
        </div>
      @empty
        <div class="text-center py-5">
          <p class="small text-dark mb-1">Belum ada aktivitas tercatat.</p>
          <p class="text-muted mb-0" style="font-size:12px">Kejadian akan muncul di sini saat ada pesanan, pembayaran, atau tiket masuk.</p>
        </div>
      @endforelse
    </div>

    @if ($activities->hasPages())
      <div class="px-4 py-3 border-top">{{ $activities->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
