@php
  $unit = [
    'monthly' => '/bulan', 'quarterly' => '/3 bulan',
    'semi_annually' => '/6 bulan', 'annually' => '/tahun',
  ];
  $cycles = $product->availableCycles();
  $firstCycleKey = array_key_first($cycles);
@endphp

<a href="{{ $product->category->productUrl($product) }}"
   class="card-public p-4 d-flex flex-column text-decoration-none position-relative h-100 prod-card {{ $product->is_featured && $product->isInStock() ? 'prod-card-featured' : '' }}">
  @if ($product->is_featured && $product->isInStock())
    <span class="badge-public-active position-absolute" style="top:1rem;right:1rem"><i class="fa-solid fa-star" style="font-size:9px"></i> Unggulan</span>
  @elseif (! $product->isInStock())
    <span class="badge-public-inactive position-absolute" style="top:1rem;right:1rem">Stok Habis</span>
  @endif

  <h3 class="fw-semibold text-dark mb-2" style="font-size:16px">{{ $product->name }}</h3>
  @if ($product->tagline)
    <p class="text-muted mb-3" style="font-size:13px;line-height:1.6">{{ $product->tagline }}</p>
  @endif

  @if ($product->features)
    <ul class="mb-3 flex-grow-1 ps-0" style="list-style:none">
      @foreach (array_slice($product->features, 0, 4) as $feature)
        <li class="d-flex align-items-start gap-2 text-muted mb-2" style="font-size:12.5px;line-height:1.7">
          <i class="fa-solid fa-check text-success flex-shrink-0" style="width:14px;margin-top:3px;text-align:center"></i>
          <span class="min-w-0">{{ $feature }}</span>
        </li>
      @endforeach
    </ul>
  @else
    <div class="flex-grow-1"></div>
  @endif

  <div class="pt-3 border-top">
    @if ($product->starting_price !== null)
      <p class="fw-bold text-dark mb-0" style="font-size:1.5rem;letter-spacing:-.01em">
        Rp {{ number_format($product->starting_price, 0, ',', '.') }}
        <span class="text-muted fw-normal" style="font-size:12px">{{ $unit[$firstCycleKey] ?? '' }}</span>
      </p>
    @else
      <p class="text-danger mb-0" style="font-size:14px">Harga belum tersedia</p>
    @endif
    <span class="btn {{ $product->isInStock() ? 'btn-theme' : 'btn-outline-secondary' }} w-100 mt-3" style="{{ $product->isInStock() ? '' : 'pointer-events:none;opacity:.6' }}">
      {{ $product->isInStock() ? 'Lihat Detail' : 'Stok Habis' }}
    </span>
  </div>
</a>

@once
<style>
  .prod-card{ transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
  .prod-card:hover{ transform:translateY(-3px); box-shadow:0 12px 28px -14px rgba(15,23,42,.25); }
  .prod-card-featured{ border-color:rgba(79,70,229,.35)!important; box-shadow:0 4px 16px -8px rgba(79,70,229,.25); }
  .prod-card-featured:hover{ box-shadow:0 14px 30px -12px rgba(79,70,229,.4); }
</style>
@endonce
