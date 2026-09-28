{{-- ══════════ Paket VPS (tema Modern) ══════════ --}}
<section class="mx-section">
  <div class="mx-container">
    <div class="mx-head">
      <div>
        <span class="mx-eyebrow">VPS & Cloud</span>
        <h2>Kontrol penuh dengan akses root</h2>
        <p>Aktif otomatis dalam hitungan menit.</p>
      </div>
      <a href="{{ route('catalog.index') }}" class="mx-link">Lihat semua paket</a>
    </div>
    <div class="mx-grid">
      @foreach ($vpsProducts as $product)
        @include('public.catalog._product-card', ['product' => $product])
      @endforeach
    </div>
  </div>
</section>
