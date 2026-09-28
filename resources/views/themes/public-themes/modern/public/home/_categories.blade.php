{{-- ══════════ Kategori (tema Modern) ══════════ --}}
<section class="mx-section">
  <div class="mx-container">
    <div class="mx-head">
      <div>
        <span class="mx-eyebrow">Layanan</span>
        <h2>Semua yang kamu butuhkan untuk online</h2>
      </div>
      <a href="{{ route('catalog.index') }}" class="mx-link">Lihat katalog</a>
    </div>
    <div class="mx-grid">
      @foreach ($categories as $category)
        <a href="{{ $category->publicUrl() }}" class="mx-tile">
          <span class="idx">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
          <h3>{{ $category->name }}</h3>
          @if ($category->description)
            <p>{{ Str::limit($category->description, 90) }}</p>
          @endif
          <span class="go">{{ $category->products_count }} paket <i class="fa-solid fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
