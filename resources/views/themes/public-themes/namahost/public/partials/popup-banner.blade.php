@php
  use App\Models\Setting;

  $popupEnabled = Setting::get('popup_banner_enabled', '0') === '1';
@endphp

@if ($popupEnabled)
  @php
    $popupImage = Setting::get('popup_banner_image');
    $popupTitle = Setting::get('popup_banner_title');
    $popupDesc  = Setting::get('popup_banner_description');
    $popupBtn   = Setting::get('popup_banner_button_text', 'Lihat Sekarang');
    $popupUrl   = Setting::get('popup_banner_link_url');
    $popupFreq  = Setting::get('popup_banner_frequency', 'once_per_day');
  @endphp

  @if ($popupImage || $popupTitle || $popupDesc)
    <div id="popupBannerOverlay" class="d-none position-fixed top-0 start-0 end-0 bottom-0 align-items-center justify-content-center p-3" style="background:rgba(15,23,42,.65);z-index:1090">
      <div class="bg-white rounded-4 shadow position-relative overflow-hidden w-100" style="max-width:28rem;animation:popupBannerIn .25s ease-out">
        <button type="button" id="popupBannerClose" aria-label="Tutup"
                class="position-absolute d-flex align-items-center justify-content-center border-0 rounded-circle text-white"
                style="top:12px;right:12px;width:32px;height:32px;background:rgba(0,0,0,.4);z-index:10">
          <i class="fa-solid fa-xmark" style="font-size:13px"></i>
        </button>

        @if ($popupImage)
          <img src="{{ route('branding.file', $popupImage) }}" alt="{{ $popupTitle }}" class="w-100" style="display:block;height:auto;max-height:70vh;object-fit:contain">
        @endif

        @if ($popupTitle || $popupDesc || $popupUrl)
          <div class="p-4">
            @if ($popupTitle)
              <h2 class="fw-bold text-dark mb-2" style="font-size:1.1rem">{{ $popupTitle }}</h2>
            @endif
            @if ($popupDesc)
              <p class="text-muted mb-3" style="font-size:14px">{{ $popupDesc }}</p>
            @endif
            @if ($popupUrl)
              <a href="{{ $popupUrl }}" class="btn btn-theme w-100 justify-content-center">{{ $popupBtn }}</a>
            @endif
          </div>
        @endif
      </div>
    </div>

    <style>
      @keyframes popupBannerIn {
        from { opacity: 0; transform: scale(.95); }
        to   { opacity: 1; transform: scale(1); }
      }
    </style>

    <script>
      (function () {
        const STORAGE_KEY = 'lumora_popup_banner_seen';
        const frequency = @json($popupFreq);

        function alreadySeen() {
          if (frequency === 'every_visit') return false;

          try {
            const raw = frequency === 'once_per_session'
              ? sessionStorage.getItem(STORAGE_KEY)
              : localStorage.getItem(STORAGE_KEY);

            if (!raw) return false;

            if (frequency === 'once_per_day') {
              const today = new Date().toISOString().slice(0, 10);
              return raw === today;
            }

            return true;
          } catch (e) {
            return false;
          }
        }

        function markSeen() {
          if (frequency === 'every_visit') return;

          try {
            if (frequency === 'once_per_session') {
              sessionStorage.setItem(STORAGE_KEY, '1');
            } else if (frequency === 'once_per_day') {
              localStorage.setItem(STORAGE_KEY, new Date().toISOString().slice(0, 10));
            }
          } catch (e) { /* Diamkan -- bukan fitur krusial. */ }
        }

        if (alreadySeen()) return;

        const overlay = document.getElementById('popupBannerOverlay');
        const closeBtn = document.getElementById('popupBannerClose');

        function show() {
          overlay.classList.remove('d-none');
          overlay.classList.add('d-flex');
        }

        function close() {
          overlay.classList.add('d-none');
          overlay.classList.remove('d-flex');
          markSeen();
        }

        setTimeout(show, 800);

        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !overlay.classList.contains('d-none')) close(); });
      })();
    </script>
  @endif
@endif
