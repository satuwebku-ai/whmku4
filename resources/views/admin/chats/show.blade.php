@extends('layouts.admin')

@section('title', 'Chat — ' . $chat->display_name)

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <a href="{{ route('admin.chats') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Live Chat</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-1">
        {{ $chat->display_name }}
        @if ($chat->channel === 'whatsapp')
          <span class="badge" style="background:#25d366;color:#fff;font-size:11px;vertical-align:middle"><i class="fa-brands fa-whatsapp"></i> WhatsApp</span>
        @endif
      </h1>
      <p class="text-muted mb-1" style="font-size:12px">
        @if ($chat->client)
          <a href="{{ route('admin.clients.details', $chat->client) }}" class="text-decoration-none text-accent">Lihat profil klien</a> ·
        @endif
        {{ $chat->email ?: 'email tidak diisi' }}
        @if ($chat->phone)
          · <a href="https://wa.me/{{ preg_replace('/\D/', '', $chat->phone) }}" target="_blank" rel="noopener" class="text-decoration-none text-success">
            <i class="fa-brands fa-whatsapp"></i> {{ $chat->phone }}
          </a>
        @endif
      </p>
      <p class="mb-0" style="font-size:12px">
        @if ($chat->assignedAdmin)
          <span class="text-muted">
            <i class="fa-solid fa-user-check text-success"></i>
            Dipegang: <b>{{ $chat->assignedAdmin->id === auth('admin')->id() ? 'Anda' : $chat->assignedAdmin->name }}</b>
          </span>
        @else
          <span class="text-warning"><i class="fa-solid fa-circle-exclamation"></i> Belum ada yang memegang</span>
        @endif

        @if (! $chat->assignedAdmin || $chat->assignedAdmin->id !== auth('admin')->id())
          <form method="POST" action="{{ route('admin.chats.claim', $chat) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link p-0 text-accent ms-1" style="font-size:12px;text-decoration:underline;vertical-align:baseline">Ambil Alih</button>
          </form>
        @endif
      </p>
    </div>

    <div class="d-flex align-items-center gap-2">
      @if ($chat->ticket_id)
        <a href="{{ route('admin.tickets.details', $chat->ticket_id) }}" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-ticket" style="font-size:11px"></i> Lihat Tiket {{ $chat->ticket?->ticket_number }}
        </a>
      @elseif ($chat->client_id)
        <form method="POST" action="{{ route('admin.chats.convert-to-ticket', $chat) }}"
              data-confirm="Jadikan percakapan ini tiket support? Transkrip chat akan disalin jadi pesan pertama, dan balasan Anda selanjutnya otomatis dikirim ke email klien."
              data-confirm-title="Jadikan Tiket" data-confirm-style="info" data-confirm-label="Ya, Jadikan Tiket">
          @csrf
          <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-ticket" style="font-size:11px"></i> Jadikan Tiket
          </button>
        </form>
      @endif
      @if ($chat->status === 'open')
        <form method="POST" action="{{ route('admin.chats.close', $chat) }}">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Tutup Percakapan</button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.chats.delete', $chat) }}"
            data-confirm="Hapus percakapan ini beserta semua pesannya?"
            data-confirm-title="Hapus Percakapan" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-regular fa-trash-can" style="font-size:11px"></i></button>
      </form>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 overflow-hidden d-flex flex-column" style="height:min(600px,70vh)">
        <div id="adminChatBody" class="flex-grow-1 overflow-y-auto px-4 py-3 d-flex flex-column gap-2" style="background:#f8fafc"></div>

        <form id="adminChatForm" class="p-3 border-top bg-white">
          @csrf
          <div id="admFileChip" class="d-none align-items-center gap-2 mb-2 px-2 py-2 rounded-3 small text-muted" style="background:#f1f5f9">
            <i class="fa-solid fa-paperclip"></i>
            <span id="admFileName" class="flex-grow-1 text-truncate"></span>
            <button type="button" id="admFileRemove" class="btn btn-link p-0 text-muted"><i class="fa-solid fa-xmark"></i></button>
          </div>

          <div class="d-flex align-items-end gap-2">
            <label class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0 mb-0" style="width:36px;height:36px;padding:0;cursor:pointer">
              <i class="fa-solid fa-paperclip" style="font-size:13px"></i>
              <input type="file" id="admFile" name="attachment" accept="image/*,application/pdf" class="d-none">
            </label>
            <textarea id="admInput" name="message" rows="1" placeholder="Tulis balasan… (Enter kirim, Shift+Enter baris baru)"
                      class="form-control form-control-sm flex-grow-1" style="resize:none;max-height:6rem"></textarea>
            <button type="submit" id="admSend" class="btn btn-primary flex-shrink-0" style="width:36px;height:36px;padding:0"><i class="fa-solid fa-paper-plane" style="font-size:12px"></i></button>
          </div>
          <p id="admError" class="d-none text-danger mt-2 mb-0" style="font-size:11px"></p>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Informasi</h2>
        <div class="mb-2">
          <p class="text-muted mb-1" style="font-size:11px">STATUS</p>
          <span class="badge {{ $chat->status === 'open' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $chat->status === 'open' ? 'Aktif' : 'Ditutup' }}</span>
        </div>
        <div class="mb-2">
          <p class="text-muted mb-1" style="font-size:11px">DIMULAI</p>
          <p class="small text-dark mb-0">{{ $chat->created_at->format('d M Y H:i') }}</p>
        </div>
        @if ($chat->page_url)
          <div class="mb-2">
            <p class="text-muted mb-1" style="font-size:11px">DARI HALAMAN</p>
            <p class="text-dark mb-0" style="font-size:11px;word-break:break-all">{{ $chat->page_url }}</p>
          </div>
        @endif
        <div>
          <p class="text-muted mb-1" style="font-size:11px">ALAMAT IP</p>
          <p class="text-dark mb-0" style="font-size:11px">{{ $chat->ip_address ?: '—' }}</p>
        </div>
      </div>
    </div>
  </div>

  <script>
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

      let lastId = 0;

      function esc(t) { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }

      function append(msg) {
        // Dari sudut pandang admin, pesan "admin" ada di kanan.
        const mine = msg.sender === 'admin';
        const bot  = msg.sender === 'bot';

        const wrap = document.createElement('div');
        wrap.className = 'd-flex ' + (mine ? 'justify-content-end' : 'justify-content-start');

        let content = '';
        if (msg.message) {
          content += esc(msg.message)
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener" class="text-decoration-underline">$1</a>')
            .replace(/\n/g, '<br>');
        }
        if (msg.attachment_url) {
          content += msg.is_image
            ? '<a href="' + msg.attachment_url + '" target="_blank"><img src="' + msg.attachment_url + '" class="mt-1 rounded-3" style="max-height:12rem"></a>'
            : '<a href="' + msg.attachment_url + '" target="_blank" class="mt-1 d-flex align-items-center gap-1 text-decoration-underline" style="font-size:11px"><i class="fa-solid fa-file-arrow-down"></i>' + esc(msg.attachment_name) + '</a>';
        }

        const bubbleStyle = mine ? 'background:#4f46e5;color:#fff'
                           : (bot ? 'background:#eef2ff;border:1px solid #c7d2fe;color:#334155'
                                  : 'background:#fff;border:1px solid #e2e8f0;color:#334155');

        wrap.innerHTML = '<div style="max-width:75%">'
          + (!mine && msg.author ? '<p class="mb-1" style="font-size:10px;color:#94a3b8">' + esc(msg.author) + '</p>' : '')
          + '<div class="rounded-4 px-3 py-2 small" style="line-height:1.6;' + bubbleStyle + '">' + content
          + '<span class="d-block mt-1" style="font-size:10px;' + (mine ? 'color:rgba(255,255,255,.6)' : 'color:#94a3b8') + '">' + esc(msg.time || '') + '</span></div></div>';

        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
        if (msg.id) lastId = Math.max(lastId, msg.id);
      }

      async function poll() {
        try {
          const res = await fetch(urlPoll + '?after=' + lastId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await res.json();
          (data.messages || []).forEach(append);
        } catch (e) { /* diam: percakapan tetap bisa dilanjutkan saat koneksi pulih */ }
      }

      fileIn.addEventListener('change', function () {
        if (!fileIn.files.length) return;
        chipNm.textContent = fileIn.files[0].name;
        chip.classList.remove('d-none'); chip.classList.add('d-flex');
      });
      document.getElementById('admFileRemove').addEventListener('click', function () {
        fileIn.value = ''; chip.classList.add('d-none'); chip.classList.remove('d-flex');
      });

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
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
          input.value = ''; fileIn.value = '';
          chip.classList.add('d-none'); chip.classList.remove('d-flex');

          // Staf ini baru selesai balas DAN tidak punya percakapan lain
          // yang menunggu -- sistem otomatis memberikan percakapan
          // tertua yang belum dipegang siapa pun. Ditampilkan sebagai
          // kartu yang bisa diklik, BUKAN auto-redirect paksa, supaya
          // staf tetap bisa menyelesaikan hal lain dulu kalau perlu.
          if (data.auto_assigned) {
            const card = document.createElement('div');
            card.className = 'mx-3 mb-2 p-3 rounded-3 small d-flex align-items-center justify-content-between gap-2';
            card.style.background = '#eef2ff';
            card.style.border = '1px solid #c7d2fe';
            card.innerHTML = '<span><i class="fa-solid fa-inbox" style="color:#6366f1"></i> Chat baru otomatis ditugaskan: <b>' + data.auto_assigned.name + '</b></span>'
              + '<a href="' + data.auto_assigned.url + '" class="btn btn-primary btn-sm flex-shrink-0">Buka</a>';
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

@endsection
