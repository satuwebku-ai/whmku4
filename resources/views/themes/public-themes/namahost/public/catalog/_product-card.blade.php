@php
  $unit = [
    'monthly' => '/bulan', 'quarterly' => '/3 bulan',
    'semi_annually' => '/6 bulan', 'annually' => '/tahun',
  ];
  $cycles = $product->availableCycles();
  $firstCycleKey = array_key_first($cycles);
  $featuredCard = $product->is_featured && $product->isInStock();
  // Promo hanya ada bila controller mengirim $productPromos dan produk ini kena kupon.
  $promo = ($productPromos ?? [])[$product->id] ?? null;
@endphp

<a href="{{ $product->category->productUrl($product) }}" class="plan {{ $featuredCard ? 'featured' : '' }}">
  @if ($featuredCard)
    <span class="plan-tag badge text-bg-warning"><i class="bi bi-star-fill" style="font-size:9px"></i> Terlaris</span>
  @elseif (! $product->isInStock())
    <span class="plan-tag badge badge-soft-secondary">Stok Habis</span>
  @endif

  @if ($product->isDepositBilled())
    <span class="badge badge-soft-warning align-self-start mb-2"><i class="bi bi-lightning-charge-fill"></i> Bayar per jam</span>
  @endif

  <h3 class="h5 mb-1" style="padding-right:5rem">{{ $product->name }}</h3>
  @if ($product->tagline)
    <p class="text-body-secondary small mb-2">{{ $product->tagline }}</p>
  @endif

  <div class="mt-2">
    @if ($product->isDepositBilled())
      @php $hourly = $product->estimatedHourlyRate(); @endphp
      @if ($hourly)
        <div class="price">Rp {{ number_format($hourly, 2, ',', '.') }} <small>/ jam</small></div>
        <p class="small text-body-secondary mb-0 mt-1">± Rp {{ number_format($hourly * 730, 0, ',', '.') }} / bulan bila menyala terus · dipotong dari saldo</p>
      @else
        <div class="price" style="font-size:1.4rem">Sesuai Pemakaian</div>
        <p class="small text-body-secondary mb-0 mt-1">Dipotong otomatis dari saldo, per jam</p>
      @endif
    @elseif ($promo)
      <div class="mb-1">
        <span class="badge text-bg-danger">Diskon {{ $promo['label'] }}</span>
        <s class="small text-body-secondary ms-1">Rp {{ number_format($promo['before'], 0, ',', '.') }}</s>
      </div>
      <div class="price">Rp {{ number_format($promo['after'], 0, ',', '.') }} <small>{{ $unit[$firstCycleKey] ?? '' }}</small></div>
    @elseif ($product->starting_price !== null)
      <div class="price">Rp {{ number_format($product->starting_price, 0, ',', '.') }} <small>{{ $unit[$firstCycleKey] ?? '' }}</small></div>
    @else
      <p class="text-danger small mb-0">Harga belum tersedia</p>
    @endif
  </div>

  @if ($product->features)
    <ul>
      @foreach (array_slice($product->features, 0, 5) as $feature)
        <li><i class="bi bi-check2"></i><span>{{ $feature }}</span></li>
      @endforeach
    </ul>
  @else
    <div class="flex-grow-1" style="min-height:1rem"></div>
  @endif

  <span class="btn {{ $featuredCard ? 'btn-primary' : ($product->isInStock() ? 'btn-outline-primary' : 'btn-outline-secondary') }} w-100" style="{{ $product->isInStock() ? '' : 'pointer-events:none;opacity:.6' }}">
    {{ $product->isInStock() ? 'Lihat detail' : 'Stok habis' }}
  </span>
</a>