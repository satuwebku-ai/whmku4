@extends('public.layout')

@php($seoTitle = 'Lisensi & Sertifikat SSL')

@section('content')
  <section class="py-4 py-lg-5">
    <div class="text-center mb-4">
      <span class="badge badge-soft-warning mb-2">LISENSI & SSL</span>
      <h1 class="display-6 fw-bold text-dark">Lisensi dan sertifikat SSL yang siap dipakai</h1>
      <p class="text-muted mb-0 mx-auto" style="max-width:40rem">Bandingkan fitur, spesifikasi, dan harga. Pilih produk yang cocok, lalu tambahkan ke keranjang untuk checkout.</p>
    </div>

    @if ($categories->isNotEmpty() && $all->isNotEmpty())
      <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
        <a href="{{ route('license.index') }}" class="btn btn-sm rounded-pill px-3 {{ $activeCategory ? 'btn-outline-secondary' : 'btn-theme' }}">Semua ({{ $all->count() }})</a>
        @foreach ($categories as $key => $cat)
          <a href="{{ route('license.index', ['kategori' => $key]) }}" class="btn btn-sm rounded-pill px-3 {{ $activeCategory === $key ? 'btn-theme' : 'btn-outline-secondary' }}">{{ $cat['label'] }} ({{ $cat['count'] }})</a>
        @endforeach
      </div>
    @endif

    <div class="row g-4">
      @forelse ($licenses as $license)
        @php($cheapest = $license->cheapestCycle())
        <div class="col-12 col-md-6 col-xl-4">
          <article class="card-public h-100 p-4 d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-theme" style="width:44px;height:44px;background:rgba(14,124,134,.1)">
                <i class="fa-solid {{ $license->category === 'ssl' ? 'fa-lock' : 'fa-key' }}"></i>
              </span>
              <span class="d-flex gap-1">
                @if ($license->brand)<span class="badge badge-soft-secondary">{{ $license->brand }}</span>@endif
                <span class="badge badge-soft-success">{{ $license->category === 'ssl' ? 'SSL' : 'Lisensi' }}</span>
              </span>
            </div>

            <h2 class="h5 fw-bold text-dark mb-1">{{ $license->name }}</h2>
            <p class="text-muted small mb-3">{{ $license->summary ?: \Illuminate\Support\Str::limit($license->description, 110) }}</p>

            @if (! empty($license->features))
              <ul class="list-unstyled small text-muted flex-grow-1 mb-3">
                @foreach (array_slice($license->features, 0, 3) as $feature)
                  <li class="d-flex gap-2 mb-1"><i class="fa-solid fa-check text-theme mt-1" style="font-size:11px"></i><span>{{ $feature }}</span></li>
                @endforeach
              </ul>
            @else
              <div class="flex-grow-1"></div>
            @endif

            <div class="border-top pt-3 mb-3">
              <div class="text-muted" style="font-size:11px">{{ count($license->availableCycles()) > 1 ? 'Mulai dari' : 'Harga' }}</div>
              <div class="d-flex align-items-baseline gap-1">
                <span class="h4 fw-bold text-dark mb-0">Rp {{ number_format($cheapest['price'], 0, ',', '.') }}</span>
                <span class="text-muted small">{{ \App\Models\Addon::CYCLE_SUFFIX[$cheapest['cycle']] ?? '' }}</span>
              </div>
              @if (! empty($license->specs['Registrasi & perpanjangan']))
                <div class="text-muted" style="font-size:11px">Registrasi & perpanjangan: {{ strtolower($license->specs['Registrasi & perpanjangan']) }}</div>
              @endif
            </div>

            <div class="d-flex gap-2">
              <a href="{{ route('license.show', $license->slug) }}" class="btn btn-outline-secondary flex-fill">Detail</a>
              @if ($license->requiresIp())
                <a href="{{ route('license.show', $license->slug) }}" class="btn btn-theme flex-fill"><i class="fa-solid fa-cart-plus me-1"></i> Pesan</a>
              @else
                <form method="POST" action="{{ route('cart.add-addon') }}" class="flex-fill">
                  @csrf
                  <input type="hidden" name="addon_id" value="{{ $license->id }}">
                  <input type="hidden" name="billing_cycle" value="{{ $cheapest['cycle'] }}">
                  <button class="btn btn-theme w-100"><i class="fa-solid fa-cart-plus me-1"></i> Keranjang</button>
                </form>
              @endif
            </div>
          </article>
        </div>
      @empty
        <div class="col-12"><div class="card-public p-5 text-center text-muted">Belum ada lisensi yang tersedia.</div></div>
      @endforelse
    </div>
  </section>
@endsection
