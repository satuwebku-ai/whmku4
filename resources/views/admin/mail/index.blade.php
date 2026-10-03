@extends('layouts.admin')

@section('title', 'Email')

@section('content')

  @php
    $when = fn ($d) => ! $d ? '' : ($d->isToday() ? $d->format('H:i') : ($d->isSameYear(now()) ? $d->translatedFormat('d M') : $d->format('d/m/y')));
    $titles = ['inbox' => 'Kotak Masuk', 'unread' => 'Belum Dibaca', 'sent' => 'Terkirim', 'closed' => 'Ditutup'];
  @endphp

  <div class="ix-app">
    @include('admin.mail._sidebar', ['folder' => $folder])

    <section class="ix-main">
      <div class="ix-head">
        <h1>
          <i class="fa-regular fa-envelope" style="color:#4f46e5"></i>
          {{ $titles[$folder] }}
          @if ($counts['unread'] > 0 && $folder !== 'closed')<small>({{ $counts['unread'] }} pesan baru)</small>@endif
        </h1>

        <form method="GET" class="ix-search">
          @if ($folder === 'closed') <input type="hidden" name="status" value="closed">@endif
          @if (in_array($folder, ['unread', 'sent'], true)) <input type="hidden" name="filter" value="{{ $folder }}">@endif
          <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari subjek, pengirim, atau isi email...">
          <button type="submit" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>

      <form id="mailBulk" method="POST" action="{{ route('admin.mail.bulk') }}">@csrf</form>

      <div class="ix-toolbar">
        <input type="checkbox" class="ix-chk" id="mailAll" title="Pilih semua">
        <div class="ix-grp">
          @if ($folder === 'closed')
            <button type="submit" form="mailBulk" name="action" value="reopen">Buka kembali</button>
          @else
            <button type="submit" form="mailBulk" name="action" value="close">Arsipkan</button>
          @endif
          <button type="submit" form="mailBulk" name="action" value="delete" class="del">Hapus</button>
        </div>

        <div class="ix-pager">
          @if ($threads->total() > 0)
            {{ $threads->firstItem() }}–{{ $threads->lastItem() }} dari {{ $threads->total() }}
          @endif
          <a class="ix-icon-btn {{ $threads->onFirstPage() ? 'disabled' : '' }}" @if (! $threads->onFirstPage()) href="{{ $threads->previousPageUrl() }}" @endif aria-label="Sebelumnya" style="{{ $threads->onFirstPage() ? 'opacity:.4;pointer-events:none' : '' }}"><i class="fa-solid fa-chevron-left"></i></a>
          <a class="ix-icon-btn" @if ($threads->hasMorePages()) href="{{ $threads->nextPageUrl() }}" @endif aria-label="Berikutnya" style="{{ $threads->hasMorePages() ? '' : 'opacity:.4;pointer-events:none' }}"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
      </div>

      <div class="ix-list">
        @forelse ($threads as $thread)
          @php
            $unread = $thread->unread_count > 0;
            $last = $thread->latestMessage;
            $snippet = $last ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $last->body), 110) : '';
            $hasFile = $last && ! empty($last->attachments);
            $sentLast = $last && ! $last->isInbound();
          @endphp
          <div class="ix-row {{ $unread ? 'unread' : '' }}">
            <input type="checkbox" class="ix-chk mail-pick" name="ids[]" value="{{ $thread->id }}" form="mailBulk" style="position:relative;z-index:2">
            <a href="{{ route('admin.mail.show', $thread) }}" class="ix-row-link">
              <span class="ix-who">
                @if ($sentLast)<i class="fa-solid fa-reply" style="font-size:10px;color:#94a3b8"></i>@endif
                {{ $thread->display_name }}
                <small class="text-muted">({{ $thread->contact_email }})</small>
              </span>
              <span class="ix-subj">
                <b>{{ $thread->subject }}</b>
                @if ($snippet !== '') — {{ $snippet }}@endif
              </span>
              <span class="ix-date">
                @if ($thread->client_id)<span class="ix-pill ok">Klien</span>@endif
                @if ($thread->messages_count > 1)<span class="ix-pill mute" title="Jumlah surat">{{ $thread->messages_count }}</span>@endif
                @if ($hasFile)<i class="fa-solid fa-paperclip"></i>@endif
                @if ($unread)<span class="ix-badge">{{ $thread->unread_count }}</span>@endif
                {{ $when($thread->last_message_at) }}
              </span>
            </a>
          </div>
        @empty
          <div class="ix-empty">
            <b>{{ request('search') ? 'Tidak ada email yang cocok.' : ($folder === 'closed' ? 'Belum ada email yang ditutup.' : ($folder === 'sent' ? 'Belum ada email terkirim.' : 'Kotak masuk kosong.')) }}</b>
            Email masuk diambil dari mailbox support tiap beberapa menit. Atur di
            <a href="{{ route('admin.settings.email') }}">Pengaturan → Email</a>.
          </div>
        @endforelse
      </div>
    </section>
  </div>

  <script @nonce>
    (function () {
      const all  = document.getElementById('mailAll');
      const form = document.getElementById('mailBulk');
      const picks = () => Array.from(document.querySelectorAll('.mail-pick'));

      all.addEventListener('change', () => picks().forEach(c => c.checked = all.checked));

      form.addEventListener('submit', function (e) { e.preventDefault(); });

      document.querySelectorAll('button[form="mailBulk"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          if (!picks().some(c => c.checked)) { alert('Pilih minimal satu email dulu.'); return; }
          if (btn.value === 'delete' && !confirm('Hapus email terpilih beserta semua surat dan lampirannya?')) return;
          let h = form.querySelector('input[name="action"]');
          if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'action'; form.appendChild(h); }
          h.value = btn.value;
          form.submit();
        });
      });
    })();
  </script>

@endsection
