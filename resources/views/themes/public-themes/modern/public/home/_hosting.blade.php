{{-- ══════════ Paket unggulan (tema Modern) ══════════ --}}
<section class="mx-section mx-section-alt">
  <div class="mx-container">
    <div class="mx-head">
      <div>
        <span class="mx-eyebrow">Hosting</span>
        <h2>Paket hosting pilihan</h2>
        <p>Mulai kecil, naik kelas kapan saja tanpa pindah server.</p>
      </div>
      <a href="{{ route('catalog.index') }}" class="mx-link">Lihat semua paket</a>
    </div>

    @if ($featured->isEmpty())
      <div class="card-public" style="padding:3rem;text-align:center">
        <p class="mb-1 text-muted">Katalog sedang disiapkan.</p>
        <p class="small text-muted mb-0">Belum ada produk yang bisa ditampilkan — tambahkan lewat menu Produk di admin panel.</p>
      </div>
    @else
      <div class="mx-grid">
        @foreach ($featured as $product)
          @include('public.catalog._product-card', ['product' => $product])
        @endforeach
      </div>
    @endif
  </div>
</section>
