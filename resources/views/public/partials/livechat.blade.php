@php
  use App\Models\Setting;

  $provider   = Setting::get('livechat_provider', 'none');
  $propertyId = Setting::get('livechat_property_id');
  $waNumber   = Setting::get('livechat_whatsapp');
  $greeting   = Setting::get('livechat_greeting', 'Halo, saya ingin bertanya tentang layanan hosting.');

  $siteName    = Setting::get('site_name', config('app.name'));
  $themeColor  = Setting::get('theme_color', '#6366F1');
  $supportMail = Setting::get('support_email');
  $jamOperasi  = Setting::get('support_hours');
@endphp

@if ($provider === 'tawkto' && $propertyId)
  <script>
    var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
    (function(){
      var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
      s1.async = true;
      s1.src = 'https://embed.tawk.to/{{ $propertyId }}';
      s1.charset = 'UTF-8';
      s1.setAttribute('crossorigin', '*');
      s0.parentNode.insertBefore(s1, s0);
    })();
  </script>

@elseif ($provider === 'crisp' && $propertyId)
  <script>
    window.$crisp = [];
    window.CRISP_WEBSITE_ID = "{{ $propertyId }}";
    (function(){
      var d = document, s = d.createElement("script");
      s.src = "https://client.crisp.chat/l.js";
      s.async = 1;
      d.getElementsByTagName("head")[0].appendChild(s);
    })();
  </script>

@elseif (in_array($provider, ['widget', 'whatsapp'], true))
  {{-- Live chat bawaan: percakapan tersimpan di database sendiri, tidak
       memuat script pihak ketiga, dan pesannya bisa dibalas dari menu
       Live Chat di admin panel. --}}

  <style>
    #chatPanel{
      transform:translateY(12px) scale(.97); opacity:0; pointer-events:none;
      transition:transform .18s ease, opacity .18s ease;
    }
    #chatPanel.chat-open{
      transform:translateY(0) scale(1); opacity:1; pointer-events:auto;
    }
    #chatToggle{ transition:transform .15s ease, box-shadow .15s ease; }
    #chatToggle:hover{ transform:translateY(-2px); box-shadow:0 10px 22px -6px rgba(76,29,149,.5); }
    #chatToggle.has-unread::before{
      content:''; position:absolute; inset:-4px; border-radius:50%;
      border:2px solid {{ $themeColor }}; opacity:.55; animation:chatRing 1.8s ease-out infinite;
    }
    @keyframes chatRing{
      0%{ transform:scale(.85); opacity:.55; }
      100%{ transform:scale(1.35); opacity:0; }
    }
    #chatBadge{ animation:chatPop .25s ease; }
    @keyframes chatPop{
      0%{ transform:scale(.5); }
      70%{ transform:scale(1.15); }
      100%{ transform:scale(1); }
    }
    #chatHeader{ position:relative; overflow:hidden; }
    #chatHeader::before{
      content:''; position:absolute; inset:0; pointer-events:none;
      background-image:radial-gradient(circle at 85% 20%, rgba(255,255,255,.1) 1px, transparent 1px);
      background-size:16px 16px;
    }
    #chatInput{ transition:border-color .15s ease; }
    #chatInput:focus{ border-color:{{ $themeColor }}; box-shadow:0 0 0 3px {{ $themeColor }}22; outline:none; }
  </style>

  <div id="chatWidget" class="position-fixed d-flex flex-column align-items-end gap-3" style="right:20px;bottom:20px;z-index:1080">

    <div id="chatPanel" class="d-none flex-column rounded-4 bg-white shadow overflow-hidden" style="width:340px;max-width:calc(100vw - 40px);height:min(520px,75vh)">

      {{-- Kepala --}}
      <div id="chatHeader" class="px-3 py-3 text-white flex-shrink-0" style="background:linear-gradient(135deg,{{ $themeColor }},#4c1d95)">
        <div class="d-flex align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-2 min-w-0">
            <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:rgba(255,255,255,.15)">
              <i class="fa-solid fa-headset"></i>
            </span>
            <div class="min-w-0">
              <p class="fw-bold text-truncate mb-0" style="font-size:14px">{{ $siteName }}</p>
              <p class="mb-0" style="font-size:11px;color:rgba(255,255,255,.7)">{{ $jamOperasi ?: 'Kami siap membantu Anda' }}</p>
            </div>
          </div>
          <button type="button" id="chatClose" class="btn btn-link p-0 text-white flex-shrink-0" style="opacity:.7" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      {{-- Daftar pesan --}}
      <div id="chatBody" class="flex-grow-1 overflow-y-auto px-3 py-3 d-flex flex-column gap-2" style="background:#f8fafc">
        <div id="chatLoading" class="text-center text-muted py-4" style="font-size:12px">Memuat percakapan…</div>
      </div>

      {{-- Tautan cepat --}}
      <div class="px-2 py-2 border-top d-flex align-items-center gap-2 flex-shrink-0 bg-white" style="font-size:11px">
        @if ($waNumber)
          <a href="https://wa.me/{{ preg_replace('/\D/', '', $waNumber) }}?text={{ urlencode($greeting) }}"
             target="_blank" rel="noopener noreferrer"
             class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill text-decoration-none" style="background:#d1fae5;color:#047857">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp
          </a>
        @endif
        @auth('client')
          <a href="{{ route('client.tickets.create') }}" class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill text-decoration-none" style="background:#f1f5f9;color:#475569">
            <i class="fa-solid fa-ticket"></i> Tiket
          </a>
        @endauth
        @if ($supportMail)
          <a href="mailto:{{ $supportMail }}" class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill text-decoration-none" style="background:#f1f5f9;color:#475569">
            <i class="fa-regular fa-envelope"></i> Email
          </a>
        @endif
      </div>

      {{-- Kotak kirim --}}
      <form id="chatForm" class="p-3 border-top flex-shrink-0 bg-white">
        @guest('client')
          <div id="chatIdentity" class="d-flex flex-column gap-2 mb-2">
            <input type="text" name="name" id="chatName" placeholder="Nama Anda" required class="form-control form-control-sm">
            <input type="email" name="email" id="chatEmail" placeholder="Email aktif" required class="form-control form-control-sm">
            <input type="tel" name="phone" id="chatPhone" placeholder="Nomor WhatsApp/Telepon" required class="form-control form-control-sm">
            <p id="chatIdentityError" class="d-none text-danger mb-0" style="font-size:11px"></p>
          </div>
        @endguest

        <div id="chatFileChip" class="d-none align-items-center gap-2 mb-2 px-2 py-2 rounded-3" style="background:#f1f5f9;font-size:12px;color:#475569">
          <i class="fa-solid fa-paperclip"></i>
          <span id="chatFileName" class="flex-grow-1 text-truncate"></span>
          <button type="button" id="chatFileRemove" class="btn btn-link p-0 text-muted"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="d-flex align-items-end gap-2">
          <label class="rounded-3 border d-flex align-items-center justify-content-center flex-shrink-0 text-muted" style="width:36px;height:36px;cursor:pointer"
                 title="Lampirkan bukti transfer atau tangkapan layar">
            <i class="fa-solid fa-paperclip" style="font-size:13px"></i>
            <input type="file" id="chatFile" name="attachment" accept="image/*,application/pdf" class="d-none">
          </label>

          <textarea id="chatInput" name="message" rows="1" placeholder="Tulis pesan…"
                    class="form-control form-control-sm flex-grow-1" style="resize:none;max-height:6rem"></textarea>

          <button type="submit" id="chatSend"
                  class="rounded-3 border-0 text-white d-flex align-items-center justify-content-center flex-shrink-0"
                  style="width:36px;height:36px;background:{{ $themeColor }}">
            <i class="fa-solid fa-paper-plane" style="font-size:13px"></i>
          </button>
        </div>

        <p id="chatError" class="d-none text-danger mt-2 mb-0" style="font-size:11px"></p>
      </form>
    </div>

    {{-- Tombol pembuka --}}
    <button type="button" id="chatToggle" aria-label="Buka chat"
            class="rounded-circle text-white border-0 shadow d-flex align-items-center justify-content-center position-relative"
            style="width:56px;height:56px;background:linear-gradient(135deg,{{ $themeColor }},#4c1d95)">
      <i id="chatIcon" class="fa-solid fa-comment-dots" style="font-size:20px"></i>
      <span id="chatBadge" class="d-none position-absolute rounded-circle align-items-center justify-content-center fw-bold text-white"
            style="top:-4px;right:-4px;min-width:20px;height:20px;padding:0 4px;font-size:10px;background:#f43f5e;border:2px solid #fff"></span>
    </button>
  </div>

  <script>
    (function () {
      const panel  = document.getElementById('chatPanel');
      const toggle = document.getElementById('chatToggle');
      const icon   = document.getElementById('chatIcon');
      const badge  = document.getElementById('chatBadge');
      const body   = document.getElementById('chatBody');
      const form   = document.getElementById('chatForm');
      const input  = document.getElementById('chatInput');
      const fileIn = document.getElementById('chatFile');
      const chip   = document.getElementById('chatFileChip');
      const chipNm = document.getElementById('chatFileName');
      const errBox = document.getElementById('chatError');
      const sendBt = document.getElementById('chatSend');

      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      const urlFetch = @json(route('chat.fetch'));
      const urlSend  = @json(route('chat.send'));

      let lastId = 0;
      let timer = null;
      let isOpen = false;
      // Penanda terpisah dari lastId — sebelum ada percakapan tersimpan,
      // lastId selamanya 0, jadi memeriksa lastId saja membuat sambutan
      // ditambahkan ulang setiap kali polling berjalan (tiap 5 detik).
      let greetingShown = false;
      let hasConversation = false;

      function esc(t) {
        const d = document.createElement('div');
        d.textContent = t ?? '';
        return d.innerHTML;
      }

      function bubble(msg) {
        const mine = msg.sender === 'user';
        const bot  = msg.sender === 'bot';

        const wrap = document.createElement('div');
        wrap.className = 'd-flex ' + (mine ? 'justify-content-end' : 'justify-content-start');

        let inner = '';

        if (!mine && msg.author) {
          inner += '<p class="mb-1" style="font-size:10px;color:#94a3b8">' + esc(msg.author) + '</p>';
        }

        const bubbleStyle = mine ? 'background:' + @json($themeColor) + ';color:#fff'
                           : (bot ? 'background:#eef2ff;color:#334155;border:1px solid #e0e7ff'
                                  : 'background:#fff;color:#334155;border:1px solid #e2e8f0');

        let content = '';

        if (msg.message) {
          // Tautan dibuat bisa diklik, tapi teksnya di-escape dulu supaya
          // pesan tidak bisa menyuntikkan HTML.
          content += esc(msg.message).replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener" style="text-decoration:underline;color:inherit">$1</a>'
          ).replace(/\n/g, '<br>');
        }

        if (msg.attachment_url) {
          if (msg.is_image) {
            content += '<a href="' + msg.attachment_url + '" target="_blank" rel="noopener">'
                     + '<img src="' + msg.attachment_url + '" class="mt-1 rounded-3" style="max-width:100%;max-height:10rem;object-fit:cover"></a>';
          } else {
            content += '<a href="' + msg.attachment_url + '" target="_blank" rel="noopener" class="mt-1 d-flex align-items-center gap-1" style="text-decoration:underline;font-size:11px;color:inherit">'
                     + '<i class="fa-solid fa-file-arrow-down"></i>' + esc(msg.attachment_name) + '</a>';
          }
        }

        inner += '<div class="rounded-4 px-3 py-2 small" style="max-width:100%;line-height:1.6;' + bubbleStyle + '">'
               + content
               + '<span class="d-block mt-1" style="font-size:10px;' + (mine ? 'color:rgba(255,255,255,.6)' : 'color:#94a3b8') + '">' + esc(msg.time || '') + '</span>'
               + '</div>';

        wrap.innerHTML = '<div style="max-width:80%' + (mine ? ';text-align:right' : '') + '">' + inner + '</div>';
        return wrap;
      }

      function append(msg) {
        body.appendChild(bubble(msg));
        body.scrollTop = body.scrollHeight;
        if (msg.id) lastId = Math.max(lastId, msg.id);
      }

      async function load() {
        try {
          const res = await fetch(urlFetch + '?after=' + lastId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await res.json();

          document.getElementById('chatLoading')?.remove();

          if (data.conversation) hasConversation = true;

          // Sambutan otomatis hanya ditampilkan sekali per kunjungan,
          // bukan setiap kali polling mengambil data.
          if (data.greeting && data.greeting.length && !greetingShown) {
            greetingShown = true;
            data.greeting.forEach(function (line, i) {
              setTimeout(() => append({ sender: 'bot', message: line, time: '' }), i * 500);
            });
          }

          (data.messages || []).forEach(append);

          // Badge hanya saat panel tertutup.
          const unread = (data.messages || []).filter(m => m.sender !== 'user').length;
          if (!isOpen && unread > 0) {
            badge.textContent = unread;
            badge.classList.remove('d-none');
            badge.classList.add('d-flex');
            toggle.classList.add('has-unread');
          }

          // Polling berkala baru dimulai setelah ada percakapan sungguhan
          // (klien sudah pernah kirim pesan). Sebelum itu tidak ada balasan
          // admin yang mungkin datang, jadi polling tiap 5 detik hanya
          // membebani server tanpa alasan.
          if (hasConversation) startPolling();
        } catch (e) {
          document.getElementById('chatLoading')?.remove();
        }
      }

      function startPolling() {
        if (timer) return;
        timer = setInterval(load, 5000);
      }

      function setOpen(open) {
        isOpen = open;

        if (open) {
          panel.classList.remove('d-none');
          panel.classList.add('d-flex');
          // requestAnimationFrame supaya transisi CSS sempat terpicu
          // (menambahkan class di frame yang sama dengan d-none->d-flex
          // tidak akan dianimasikan browser).
          requestAnimationFrame(() => panel.classList.add('chat-open'));
        } else {
          panel.classList.remove('chat-open');
          setTimeout(() => {
            if (!isOpen) { panel.classList.add('d-none'); panel.classList.remove('d-flex'); }
          }, 180);
        }

        icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-comment-dots';
        icon.style.fontSize = '20px';

        if (open) {
          badge.classList.add('d-none');
          badge.classList.remove('d-flex');
          toggle.classList.remove('has-unread');
          load();
          setTimeout(() => input.focus(), 100);
        }
      }

      toggle.addEventListener('click', () => setOpen(panel.classList.contains('d-none')));
      document.getElementById('chatClose').addEventListener('click', () => setOpen(false));

      // Lampiran
      fileIn.addEventListener('change', function () {
        if (!fileIn.files.length) return;
        chipNm.textContent = fileIn.files[0].name;
        chip.classList.remove('d-none');
        chip.classList.add('d-flex');
      });

      document.getElementById('chatFileRemove').addEventListener('click', function () {
        fileIn.value = '';
        chip.classList.add('d-none');
        chip.classList.remove('d-flex');
      });

      // Enter mengirim, Shift+Enter baris baru.
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          form.requestSubmit();
        }
      });

      function validIdentity() {
        const identityBox = document.getElementById('chatIdentity');
        const errIdentity = document.getElementById('chatIdentityError');

        // Sudah pernah terisi & tersimpan sebelumnya (kotaknya sudah
        // disembunyikan setelah pesan pertama berhasil) -- tidak perlu
        // divalidasi ulang untuk pesan-pesan berikutnya.
        if (!identityBox || identityBox.classList.contains('d-none')) return true;

        const name = document.getElementById('chatName')?.value.trim();
        const email = document.getElementById('chatEmail')?.value.trim();
        const phone = document.getElementById('chatPhone')?.value.trim();
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email || '');
        const phoneOk = (phone || '').replace(/\D/g, '').length >= 8;

        if (!name || !email || !phone) {
          errIdentity.textContent = 'Nama, email, dan nomor telepon wajib diisi sebelum mengirim pesan.';
          errIdentity.classList.remove('d-none');
          return false;
        }
        if (!emailOk) {
          errIdentity.textContent = 'Masukkan alamat email yang valid (contoh: nama@email.com).';
          errIdentity.classList.remove('d-none');
          return false;
        }
        if (!phoneOk) {
          errIdentity.textContent = 'Masukkan nomor telepon yang valid (minimal 8 digit).';
          errIdentity.classList.remove('d-none');
          return false;
        }

        errIdentity.classList.add('d-none');
        return true;
      }

      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');

        if (!validIdentity()) return;

        const fd = new FormData(form);

        if (!fd.get('message')?.trim() && !fileIn.files.length) return;

        sendBt.disabled = true;

        try {
          const res = await fetch(urlSend, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          });

          const data = await res.json();

          if (!res.ok || !data.ok) {
            errBox.textContent = data.message || 'Gagal mengirim pesan.';
            errBox.classList.remove('d-none');
            return;
          }

          append(data.message);
          if (data.bot_message) append(data.bot_message);
          input.value = '';
          fileIn.value = '';
          chip.classList.add('d-none');
          chip.classList.remove('d-flex');
          document.getElementById('chatIdentity')?.classList.add('d-none');
          startPolling();
        } catch (err) {
          errBox.textContent = 'Tidak bisa terhubung. Periksa koneksi Anda.';
          errBox.classList.remove('d-none');
        } finally {
          sendBt.disabled = false;
        }
      });

      // Ambil sekali di awal supaya badge muncul walau panel belum dibuka.
      // Polling berkala baru menyusul otomatis lewat load() kalau memang
      // sudah ada percakapan (lihat hasConversation di atas).
      load();
    })();
  </script>
@endif
