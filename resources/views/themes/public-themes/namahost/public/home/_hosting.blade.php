{{-- ══════════ Paket unggulan (tema NamaHost) ══════════ --}}
<section id="paket" class="py-5 bg-body-tertiary">
  <div class="container">
    <div class="text-center mb-5">
      <h2>Pilih paket hosting</h2>
      <p class="text-body-secondary mb-0">Mulai kecil, naik kelas kapan saja tanpa pindah server.</p>
    </div>

    @if ($featured->isEmpty())
      <div class="card-public p-5 text-center">
        <p class="mb-1 text-body-secondary">Katalog sedang disiapkan.</p>
        <p class="small text-body-secondary mb-0">Belum ada produk yang bisa ditampilkan — tambahkan lewat menu Produk di admin panel.</p>
      </div>
    @else
      <div class="row g-4 justify-content-center">
        @foreach ($featured as $product)
          <div class="col-md-6 col-lg-4">
            @include('public.catalog._product-card', ['product' => $product])
          </div>
        @endforeach
      </div>
      <div class="text-center mt-5">
        <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary">Lihat semua paket <i class="bi bi-arrow-right"></i></a>
      </div>
    @endif
  </div>
</section>
