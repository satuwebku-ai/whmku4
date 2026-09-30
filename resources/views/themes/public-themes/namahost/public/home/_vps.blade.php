{{-- ══════════ Paket VPS (tema NamaHost) ══════════ --}}
<section class="py-5">
  <div class="container">
    <div class="card mb-4"><div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex gap-3 align-items-center">
        <span class="tile t-indigo"><i class="bi bi-hdd-rack"></i></span>
        <div>
          <h2 class="h5 mb-1">Butuh kendali penuh? Coba VPS NVMe</h2>
          <p class="mb-0 text-body-secondary">Akses root dan aktif otomatis dalam hitungan menit.</p>
        </div>
      </div>
      <a href="{{ route('catalog.vps') }}" class="btn btn-primary">Lihat semua paket</a>
    </div></div>

    <div class="row g-4">
      @foreach ($vpsProducts as $product)
        <div class="col-md-6 col-lg-4">
          @include('public.catalog._product-card', ['product' => $product])
        </div>
      @endforeach
    </div>
  </div>
</section>
