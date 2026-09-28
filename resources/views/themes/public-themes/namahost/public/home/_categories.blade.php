{{-- ══════════ Kategori layanan (tema NamaHost) ══════════ --}}
@php $tiles = ['t-teal', 't-indigo', 't-coral', 't-amber']; @endphp
<section class="py-5">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
      <div>
        <span class="badge text-bg-warning mb-2">Layanan</span>
        <h2 class="mb-0">Semua yang Anda butuhkan untuk online</h2>
      </div>
      <a href="{{ route('catalog.index') }}" class="fw-semibold text-decoration-none">Lihat katalog <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
      @foreach ($categories as $category)
        <div class="col-md-6 col-lg-4">
          <a href="{{ $category->publicUrl() }}" class="feat d-flex gap-3 h-100 text-decoration-none text-reset">
            <span class="tile {{ $tiles[$loop->index % count($tiles)] }}"><i class="bi bi-server"></i></span>
            <div>
              <h3 class="h6 mb-1">{{ $category->name }}</h3>
              @if ($category->description)
                <p class="text-body-secondary small mb-2">{{ Str::limit($category->description, 90) }}</p>
              @endif
              <span class="small fw-semibold text-primary">{{ $category->products_count }} paket <i class="bi bi-arrow-right"></i></span>
            </div>
          </a>
        </div>
      @endforeach
    </div>
  </div>
</section>
