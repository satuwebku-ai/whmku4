{{-- ══════════ Banner promo (tema NamaHost) ══════════ --}}
@if ($banners->isNotEmpty())
  <section class="container py-4">
    <div class="position-relative rounded-4 overflow-hidden" id="nhPromoCarousel">
      @foreach ($banners as $i => $banner)
        @php
          // Judul "-" = penanda "tanpa judul" (field wajib di form admin).
          $bannerTitle = trim((string) $banner->title) === '-' ? '' : $banner->title;
        @endphp

        <div class="nh-promo-slide {{ $i === 0 ? '' : 'd-none' }}">
          @if ($banner->link_url)
            <a href="{{ $banner->link_url }}" @if ($banner->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif class="d-block position-relative">
          @else
            <div class="position-relative">
          @endif

            <img src="{{ route('banner.file', $banner->image) }}" alt="{{ $banner->title }}" class="w-100 d-block" style="height:auto">

            @if ($bannerTitle || $banner->subtitle || $banner->button_text)
              <div class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center" style="background:linear-gradient(to right, rgba(0,0,0,.6), rgba(0,0,0,.2) 60%, transparent)">
                <div class="px-4 px-lg-5" style="max-width:34rem">
                  @if ($bannerTitle)
                    <h2 class="h4 text-white fw-bold mb-1">{{ $bannerTitle }}</h2>
                  @endif
                  @if ($banner->subtitle)
                    <p class="text-white opacity-75 small mb-3">{{ $banner->subtitle }}</p>
                  @endif
                  @if ($banner->button_text)
                    <span class="btn btn-accent">{{ $banner->button_text }}</span>
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
            <button type="button" class="nh-promo-dot rounded-circle border-0 p-0" aria-label="Banner {{ $i + 1 }}" style="width:8px;height:8px;background:{{ $i === 0 ? '#fff' : 'rgba(255,255,255,.4)' }}"></button>
          @endforeach
        </div>
      @endif
    </div>
  </section>

  @if ($banners->count() > 1)
    <script @nonce>
      (function () {
        var slides = document.querySelectorAll('#nhPromoCarousel .nh-promo-slide');
        var dots = document.querySelectorAll('#nhPromoCarousel .nh-promo-dot');
        var current = 0;

        function show(i) {
          slides.forEach(function (s, n) { s.classList.toggle('d-none', n !== i); });
          dots.forEach(function (d, n) { d.style.background = n === i ? '#fff' : 'rgba(255,255,255,.4)'; });
          current = i;
        }

        dots.forEach(function (d, n) { d.addEventListener('click', function () { show(n); }); });
        setInterval(function () { show((current + 1) % slides.length); }, 5000);
      })();
    </script>
  @endif
@endif