{{--
    Toast notification bersama — dipakai layout admin, klien, dan publik
    supaya gaya pemberitahuannya konsisten di seluruh aplikasi (sebelumnya
    tiap layout punya gaya sendiri-sendiri).

    Muncul dari kanan atas, hilang sendiri setelah beberapa detik, tapi
    tetap bisa ditutup manual. Sengaja TIDAK memakai library eksternal —
    murni CSS + JS singkat, supaya tidak menambah ketergantungan CDN yang
    pernah bermasalah sebelumnya.
--}}
@php
  $toasts = [];

  if (session('success')) {
      $toasts[] = ['type' => 'success', 'message' => session('success')];
  }

  if (session('error')) {
      $toasts[] = ['type' => 'error', 'message' => session('error')];
  }

  if (session('warning')) {
      $toasts[] = ['type' => 'warning', 'message' => session('warning')];
  }

  if (session('info')) {
      $toasts[] = ['type' => 'info', 'message' => session('info')];
  }
@endphp

@if (! empty($toasts))
  <div id="toastWrap" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-[min(22rem,calc(100vw-2rem))]">
    @foreach ($toasts as $i => $toast)
      @php
        $style = match ($toast['type']) {
            'success' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'icon' => 'fa-circle-check', 'iconColor' => 'text-emerald-500'],
            'error'   => ['bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'text' => 'text-rose-800',    'icon' => 'fa-circle-exclamation', 'iconColor' => 'text-rose-500'],
            'warning' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-800',   'icon' => 'fa-triangle-exclamation', 'iconColor' => 'text-amber-500'],
            default   => ['bg' => 'bg-sky-50',     'border' => 'border-sky-200',     'text' => 'text-sky-800',     'icon' => 'fa-circle-info', 'iconColor' => 'text-sky-500'],
        };
      @endphp
      <div class="lumora-toast {{ $style['bg'] }} {{ $style['border'] }} {{ $style['text'] }}
                  border rounded-lg shadow-lg px-4 py-3 flex items-start gap-3 text-sm"
           style="animation: lumoraToastIn .25s ease-out {{ $i * 0.08 }}s both">
        <i class="fa-solid {{ $style['icon'] }} {{ $style['iconColor'] }} mt-0.5 shrink-0"></i>
        <span class="flex-1 leading-snug">{{ $toast['message'] }}</span>
        <button type="button" onclick="lumoraDismissToast(this.parentElement)"
                class="shrink-0 opacity-40 hover:opacity-100 transition-opacity" aria-label="Tutup">
          <i class="fa-solid fa-xmark text-xs"></i>
        </button>
      </div>
    @endforeach
  </div>

  <style>
    @keyframes lumoraToastIn {
      from { opacity: 0; transform: translateX(1rem); }
      to   { opacity: 1; transform: translateX(0); }
    }
    .lumora-toast.lumora-toast-out {
      opacity: 0;
      transform: translateX(1rem);
      transition: opacity .2s ease-in, transform .2s ease-in;
    }
  </style>

  <script>
    function lumoraDismissToast(el) {
      if (!el) return;
      el.classList.add('lumora-toast-out');
      setTimeout(() => el.remove(), 200);
    }

    // Pesan galat sengaja dibiarkan LEBIH LAMA (8 detik) daripada pesan
    // sukses (4 detik) -- galat biasanya perlu dibaca lebih teliti, dan
    // kadang berisi keterangan teknis yang tidak bisa dibaca sekilas.
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.lumora-toast').forEach((el) => {
        const isError = el.classList.contains('bg-rose-50') || el.classList.contains('bg-amber-50');
        setTimeout(() => lumoraDismissToast(el), isError ? 8000 : 4000);
      });
    });
  </script>
@endif
