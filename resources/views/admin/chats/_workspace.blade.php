@php
  $me = auth('admin')->user();
  $initials = fn ($n) => strtoupper(mb_substr(trim($n), 0, 1) . (preg_match('/\s(\S)/u', trim($n), $m) ? $m[1] : ''));
  $when = fn ($d) => ! $d ? '' : ($d->isToday() ? $d->format('H:i') : ($d->isYesterday() ? 'Kemarin' : ($d->isSameYear(now()) ? $d->translatedFormat('d M') : $d->format('d/m/y'))));
  $chIcon = ['whatsapp' => ['wa', 'fa-brands fa-whatsapp'], 'email' => ['em', 'fa-regular fa-envelope'], 'web' => ['web', 'fa-solid fa-globe']];
  $chat = $chat ?? null;
@endphp

<link rel="stylesheet" href="{{ asset('assets/css/lumora-inbox.css') }}">

<div class="ix-app ix-chat {{ $chat ? 'has-chat' : '' }}">

  {{-- ═══ Kiri: daftar percakapan ═══ --}}
  <aside class="ix-clist">
    <div class="ix-me">
      <span class="ix-av me">{{ $initials($me->name) }}</span>
      <div style="min-width:0">
        <div class="nm">{{ $me->name }}</div>
        <div class="rl text-capitalize">{{ $me->role }}</div>
      </div>
      <a href="{{ route('admin.settings.livechat') }}" class="ix-icon-btn" title="Atur Widget"><i class="fa-solid fa-gear"></i></a>
    </div>

    <form method="GET" action="{{ route('admin.chats') }}" class="ix-search">
      @if ($tab === 'closed')<input type="hidden" name="status" value="closed">@endif
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, WhatsApp...">
      <button type="submit" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <div class="ix-tabs">
      <a href="{{ route('admin.chats') }}" class="{{ $tab !== 'closed' ? 'active' : '' }}">
        <i class="fa-regular fa-comment"></i> Aktif ({{ $counts['open'] }})
        @if ($counts['unread'] > 0)<span class="ix-badge" style="margin-left:3px">{{ $counts['unread'] }}</span>@endif
      </a>
      <a href="{{ route('admin.chats', ['status' => 'closed']) }}" class="{{ $tab === 'closed' ? 'active' : '' }}">
        <i class="fa-solid fa-check-double"></i> Ditutup ({{ $counts['closed'] }})
      </a>
    </div>

    <div class="lbl">Percakapan terbaru</div>

    <div class="ix-citems">
      @forelse ($conversations as $c)
        @php
          $last = $c->latestMessage;
          $preview = $last ? ($last->message ?: 'Lampiran') : 'Belum ada pesan';
          $ch = $chIcon[$c->channel] ?? $chIcon['web'];
        @endphp
        <a href="{{ route('admin.chats.show', $c) }}" class="ix-ci {{ $c->unread_for_admin > 0 ? 'unread' : '' }} {{ $chat && $chat->id === $c->id ? 'active' : '' }}">
          <span class="ix-av">{{ $c->initials }}<span class="ix-ch {{ $ch[0] }}"><i class="{{ $ch[1] }}"></i></span></span>
          <div class="mid">
            <div class="t1"><b>{{ $c->display_name }}</b><span>{{ $when($c->last_message_at) }}</span></div>
            <div class="t2">
              <em>{{ $last && $last->sender === 'admin' ? 'Anda: ' : '' }}{{ \Illuminate\Support\Str::limit($preview, 48) }}</em>
              @if ($c->unread_for_admin > 0)<span class="ix-badge">{{ $c->unread_for_admin }}</span>@endif
            </div>
            @if ($c->assignedAdmin)
              <div class="t3"><i class="fa-solid fa-user" style="font-size:8px"></i> {{ $c->assignedAdmin->id === $me->id ? 'Anda' : $c->assignedAdmin->name }} · {{ $c->client_id ? 'Klien' : 'Tamu' }}</div>
            @elseif ($c->status === 'open')
              <div class="t3 warn"><i class="fa-solid fa-circle-exclamation" style="font-size:8px"></i> Belum dipegang · {{ $c->client_id ? 'Klien' : 'Tamu' }}</div>
            @endif
          </div>
        </a>
      @empty
        <div class="ix-empty">
          <b>{{ request('search') ? 'Tidak ada yang cocok.' : 'Belum ada percakapan.' }}</b>
          Pastikan widget aktif di <a href="{{ route('admin.settings.livechat') }}">Pengaturan → Live Chat</a>.
        </div>
      @endforelse
    </div>

    @if ($conversations->hasPages())
      <div class="ix-cpager">
        @if ($conversations->onFirstPage()) <span style="color:#cbd5e1">‹ Sebelumnya</span> @else <a href="{{ $conversations->previousPageUrl() }}">‹ Sebelumnya</a> @endif
        @if ($conversations->hasMorePages()) <a href="{{ $conversations->nextPageUrl() }}">Berikutnya ›</a> @else <span style="color:#cbd5e1">Berikutnya ›</span> @endif
      </div>
    @endif
  </aside>

  {{-- ═══ Kanan: percakapan ═══ --}}
  <section class="ix-conv">
    @if (! $chat)
      <div class="ix-none">
        <i class="fa-regular fa-comments"></i>
        <div>Pilih percakapan di kiri untuk mulai membalas.</div>
      </div>
    @else
      @php $ch = $chIcon[$chat->channel] ?? $chIcon['web']; $mine = $chat->assignedAdmin && $chat->assignedAdmin->id === $me->id; @endphp

      <div class="ix-conv-h">
        <a href="{{ route('admin.chats', $chat->status === 'closed' ? ['status' => 'closed'] : []) }}" class="ix-icon-btn ix-back" aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></a>
        <span class="ix-av">{{ $chat->initials }}<span class="ix-ch {{ $ch[0] }}"><i class="{{ $ch[1] }}"></i></span></span>
        <div style="min-width:0">
          <div class="nm">
            {{ $chat->display_name }}
            @if ($chat->client_id)<span class="ix-pill ok">Klien</span>@else<span class="ix-pill mute">Tamu</span>@endif
            @if ($chat->status === 'closed')<span class="ix-pill mute">Ditutup</span>@endif
          </div>
          <div class="sb">
            @if ($chat->client)<a href="{{ route('admin.clients.details', $chat->client) }}" class="text-decoration-none">Profil klien</a> · @endif
            {{ $chat->email ?: 'email tidak diisi' }}
            @if ($chat->phone) · <a href="https://wa.me/{{ preg_replace('/\D/', '', $chat->phone) }}" target="_blank" rel="noopener" class="text-decoration-none text-success"><i class="fa-brands fa-whatsapp"></i> {{ $chat->phone }}</a>@endif
          </div>
        </div>

        <div class="ix-actions">
          <button type="button" class="ix-icon-btn" id="admInfoBtn" title="Informasi"><i class="fa-solid fa-circle-info"></i></button>
          @if ($chat->ticket_id)
            <a href="{{ route('admin.tickets.details', $chat->ticket_id) }}" class="ix-icon-btn" title="Lihat tiket {{ $chat->ticket?->ticket_number }}"><i class="fa-solid fa-ticket"></i></a>
          @elseif ($chat->client_id)
            <form method="POST" action="{{ route('admin.chats.convert-to-ticket', $chat) }}"
                  data-confirm="Jadikan percakapan ini tiket support? Transkrip chat akan disalin jadi pesan pertama, dan balasan Anda selanjutnya otomatis dikirim ke email klien."
                  data-confirm-title="Jadikan Tiket" data-confirm-style="info" data-confirm-label="Ya, Jadikan Tiket">
              @csrf
              <button type="submit" class="ix-icon-btn" title="Jadikan tiket"><i class="fa-solid fa-ticket"></i></button>
            </form>
          @endif
          @if ($chat->status === 'open')
            <form method="POST" action="{{ route('admin.chats.close', $chat) }}">@csrf
              <button type="submit" class="ix-icon-btn" title="Tutup percakapan"><i class="fa-solid fa-check"></i></button>
            </form>
          @endif
          <form method="POST" action="{{ route('admin.chats.delete', $chat) }}"
                data-confirm="Hapus percakapan ini beserta semua pesannya?"
                data-confirm-title="Hapus Percakapan" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
            @csrf @method('DELETE')
            <button type="submit" class="ix-icon-btn danger" title="Hapus"><i class="fa-regular fa-trash-can"></i></button>
          </form>
        </div>
      </div>

      <div class="ix-claim {{ $mine ? 'mine' : '' }}">
        @if ($mine)
          <i class="fa-solid fa-user-check"></i> Dipegang: <b>Anda</b>
        @elseif ($chat->assignedAdmin)
          <i class="fa-solid fa-user"></i> Dipegang: <b>{{ $chat->assignedAdmin->name }}</b>
          <form method="POST" action="{{ route('admin.chats.claim', $chat) }}" class="d-inline">@csrf<button type="submit">Ambil Alih</button></form>
        @else
          <i class="fa-solid fa-circle-exclamation"></i> Belum ada yang memegang
          <form method="POST" action="{{ route('admin.chats.claim', $chat) }}" class="d-inline">@csrf<button type="submit">Ambil Alih</button></form>
        @endif
      </div>

      <div class="ix-info" id="admInfo">
        <div><b>STATUS</b>{{ $chat->status === 'open' ? 'Aktif' : 'Ditutup' }}</div>
        <div><b>DIMULAI</b>{{ $chat->created_at->format('d M Y H:i') }}</div>
        <div><b>KANAL</b>{{ ['whatsapp' => 'WhatsApp', 'email' => 'Email'][$chat->channel] ?? 'Website' }}</div>
        @if ($chat->page_url)<div style="max-width:24rem;word-break:break-all"><b>DARI HALAMAN</b>{{ $chat->page_url }}</div>@endif
        <div><b>ALAMAT IP</b>{{ $chat->ip_address ?: '—' }}</div>
      </div>

      <div id="adminChatBody" class="ix-body"></div>

      <form id="adminChatForm" class="ix-compose-bar">
        @csrf
        <div class="ix-chip" id="admFileChip">
          <i class="fa-solid fa-paperclip"></i><span id="admFileName"></span>
          <button type="button" id="admFileRemove"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="row">
          <label class="ix-round mb-0" title="Lampirkan berkas">
            <i class="fa-solid fa-paperclip" style="font-size:13px"></i>
            <input type="file" id="admFile" name="attachment" accept="image/*,application/pdf" class="d-none">
          </label>
          @php $chatTpls = \App\Models\MailTemplate::orderBy('sort')->orderBy('id')->get(['id', 'title', 'body']); @endphp
          @if ($chatTpls->isNotEmpty())
            <select id="admTpl" class="ix-tpl" title="Template balasan" style="max-width:120px">
              <option value="">⚡ Template</option>
              @foreach ($chatTpls as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
            </select>
          @endif
          <textarea id="admInput" name="message" rows="1" placeholder="Tulis pesan… (Enter kirim, Shift+Enter baris baru)"></textarea>
          <button type="submit" id="admSend" class="ix-round send" aria-label="Kirim"><i class="fa-solid fa-paper-plane" style="font-size:13px"></i></button>
        </div>
        <p id="admError" class="ix-err d-none mb-0"></p>
      </form>

      <script @nonce>
        (function () {
          const body   = document.getElementById('adminChatBody');
          const form   = document.getElementById('adminChatForm');
          const input  = document.getElementById('admInput');
          const fileIn = document.getElementById('admFile');
          const chip   = document.getElementById('admFileChip');
          const chipNm = document.getElementById('admFileName');
          const errBox = document.getElementById('admError');
          const sendBt = document.getElementById('admSend');

          const token    = document.querySelector('meta[name="csrf-token"]')?.content;
          const urlPoll  = @json(route('admin.chats.poll', $chat));
          const urlReply = @json(route('admin.chats.reply', $chat));
          const guestIni = @json($chat->initials);

          let lastId = 0;

          function esc(t) { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }
          function ini(n) {
            const p = String(n || 'T').trim().split(/\s+/);
            return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
          }

          function append(msg) {
            if (msg.id && document.querySelector('[data-mid="' + msg.id + '"]')) return;

            const out = msg.sender === 'admin';
            const bot = msg.sender === 'bot';

            let content = '';
            if (msg.message) {
              content += esc(msg.message)
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
                .replace(/\n/g, '<br>');
            }
            if (msg.attachment_url) {
              content += msg.is_image
                ? '<a href="' + msg.attachment_url + '" target="_blank"><img src="' + msg.attachment_url + '" alt=""></a>'
                : '<a href="' + msg.attachment_url + '" target="_blank" style="display:block;margin-top:4px;font-size:12px"><i class="fa-solid fa-file-arrow-down"></i> ' + esc(msg.attachment_name) + '</a>';
            }

            const av = out ? ini(msg.author) : (bot ? '<i class="fa-solid fa-robot"></i>' : guestIni);
            const who = (!out && bot) ? 'Bot' : (out ? (msg.author || 'Tim Support') : '');

            const el = document.createElement('div');
            el.className = 'ix-msg ' + (out ? 'out' : '') + ' ' + (bot ? 'bot' : '');
            if (msg.id) el.dataset.mid = msg.id;
            el.innerHTML = '<span class="ix-av sm ' + (out ? 'me' : '') + '">' + av + '</span>'
              + '<div><div class="ix-bubble">' + content + '</div>'
              + '<div class="ix-time">' + (who ? esc(who) + ' · ' : '') + esc(msg.time || '') + '</div></div>';

            body.appendChild(el);
            body.scrollTop = body.scrollHeight;
            if (msg.id) lastId = Math.max(lastId, msg.id);
          }

          async function poll() {
            try {
              const res = await fetch(urlPoll + '?after=' + lastId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
              const data = await res.json();
              (data.messages || []).forEach(append);
            } catch (e) { /* diam: lanjut saat koneksi pulih */ }
          }

          const tpls = @json($chatTpls->keyBy('id'));
          const tplSel = document.getElementById('admTpl');
          if (tplSel) {
            tplSel.addEventListener('change', function () {
              const t = tpls[tplSel.value]; tplSel.value = '';
              if (!t) return;
              input.value = t.body
                .split('{nama}').join(@json($chat->display_name))
                .split('{email}').join(@json((string) $chat->email))
                .split('{site}').join(@json((string) \App\Models\Setting::get('site_name', config('app.name'))))
                .split('{admin}').join(@json($me->name));
              input.focus();
              input.dispatchEvent(new Event('input'));
            });
          }

          document.getElementById('admInfoBtn').addEventListener('click', function () {
            document.getElementById('admInfo').classList.toggle('open');
          });

          fileIn.addEventListener('change', function () {
            if (!fileIn.files.length) return;
            chipNm.textContent = fileIn.files[0].name;
            chip.classList.add('show');
          });
          document.getElementById('admFileRemove').addEventListener('click', function () {
            fileIn.value = ''; chip.classList.remove('show');
          });

          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
          });
          input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 96) + 'px';
          });

          form.addEventListener('submit', async function (e) {
            e.preventDefault();
            errBox.classList.add('d-none');

            const fd = new FormData(form);
            if (!fd.get('message')?.trim() && !fileIn.files.length) return;

            sendBt.disabled = true;
            try {
              const res = await fetch(urlReply, {
                method: 'POST', body: fd,
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              });
              const data = await res.json();
              if (!res.ok || !data.ok) {
                errBox.textContent = data.message || 'Gagal mengirim.';
                errBox.classList.remove('d-none');
                return;
              }
              append(data.message);
              input.value = ''; input.style.height = 'auto'; fileIn.value = ''; chip.classList.remove('show');

              if (data.email_failed) {
                errBox.textContent = 'Balasan tersimpan, tetapi email ke pengunjung gagal terkirim. Periksa Pengaturan → Email.';
                errBox.classList.remove('d-none');
              }

              if (data.auto_assigned) {
                const card = document.createElement('div');
                card.className = 'ix-auto';
                card.innerHTML = '<span><i class="fa-solid fa-inbox" style="color:#6366f1"></i> Chat baru otomatis ditugaskan: <b>' + esc(data.auto_assigned.name) + '</b></span>'
                  + '<a href="' + data.auto_assigned.url + '" class="ix-btn pri" style="padding:.3rem .8rem;font-size:12px">Buka</a>';
                form.parentElement.insertBefore(card, form);
              }
            } catch (err) {
              errBox.textContent = 'Tidak bisa terhubung.';
              errBox.classList.remove('d-none');
            } finally { sendBt.disabled = false; }
          });

          poll();
          setInterval(poll, 5000);
        })();
      </script>
    @endif
  </section>
</div>
