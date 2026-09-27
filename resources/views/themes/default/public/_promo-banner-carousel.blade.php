{{--
    Carousel banner promo bersama — versi Bootstrap. Butuh variabel
    $banners (koleksi PromoBanner, sudah difilter live() dan forPage()
    dari controller masing-masing).
--}}
@if ($banners->isNotEmpty())
  <div class="position-relative rounded-4 overflow-hidden mb-4" id="promoBannerCarousel">
    @foreach ($banners as $i => $banner)
      <div class="promo-slide {{ $i === 0 ? '' : 'd-none' }}" data-slide="{{ $i }}">
        @if ($banner->link_url)
          <a href="{{ $banner->link_url }}" @if ($banner->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif class="d-block position-relative">
        @else
          <div class="position-relative">
        @endif

          <img src="{{ route('banner.file', $banner->image) }}" alt="{{ $banner->title }}" class="w-100" style="display:block;height:auto">

          @php
            // Judul "-" dipakai sebagai penanda "tanpa judul" (field ini
            // wajib diisi di form admin), jadi diperlakukan sama seperti
            // kosong supaya bayangan gelap tidak muncul kalau gambar
            // banner sudah punya teksnya sendiri.
            $bannerTitle = trim((string) $banner->title) === '-' ? '' : $banner->title;
          @endphp

          @if ($bannerTitle || $banner->subtitle || $banner->button_text)
            <div class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center" style="background:linear-gradient(to right, rgba(0,0,0,.6), rgba(0,0,0,.2) 60%, transparent)">
              <div class="px-4" style="max-width:32rem">
                @if ($bannerTitle)
                  <h2 class="text-white fw-bold mb-1" style="font-size:1.4rem">{{ $bannerTitle }}</h2>
                @endif
                @if ($banner->subtitle)
                  <p class="text-white mb-3" style="opacity:.8;font-size:14px">{{ $banner->subtitle }}</p>
                @endif
                @if ($banner->button_text)
                  <span class="btn btn-theme d-inline-flex">{{ $banner->button_text }}</span>
                @endif
              </div>
            </div>
          @endif

        @if ($banner->link_url)
          </a>
        @else
          </div>
        @endif
      </div>
    @endforeach

    @if ($banners->count() > 1)
      <div class="position-absolute d-flex gap-2" style="bottom:12px;left:50%;transform:translateX(-50%)">
        @foreach ($banners as $i => $banner)
          <button type="button" class="promo-dot rounded-circle border-0" data-dot="{{ $i }}" style="width:8px;height:8px;background:{{ $i === 0 ? '#fff' : 'rgba(255,255,255,.4)' }}"></button>
        @endforeach
      </div>
    @endif
  </div>

  @if ($banners->count() > 1)
    <script>
      (function () {
        const slides = document.querySelectorAll('#promoBannerCarousel .promo-slide');
        const dots = document.querySelectorAll('#promoBannerCarousel .promo-dot');
        let current = 0;

        function show(index) {
          slides.forEach((s, i) => s.classList.toggle('d-none', i !== index));
          dots.forEach((d, i) => { d.style.background = i === index ? '#fff' : 'rgba(255,255,255,.4)'; });
          current = index;
        }

        dots.forEach(dot => dot.addEventListener('click', () => show(parseInt(dot.dataset.dot))));

        setInterval(() => show((current + 1) % slides.length), 5000);
      })();
    </script>
  @endif
@endif
