@php
  $unit = [
    'monthly' => '/bulan', 'quarterly' => '/3 bulan',
    'semi_annually' => '/6 bulan', 'annually' => '/tahun',
  ];
  $cycles = $product->availableCycles();
  $firstCycleKey = array_key_first($cycles);
  $featuredCard = $product->is_featured && $product->isInStock();
@endphp

<a href="{{ $product->category->productUrl($product) }}" class="mx-pcard {{ $featuredCard ? 'mx-pcard-featured' : '' }}">
  @if ($featuredCard)
    <span class="mx-tag"><i class="fa-solid fa-star" style="font-size:9px"></i> Unggulan</span>
  @elseif (! $product->isInStock())
    <span class="mx-tag mx-tag-muted">Stok Habis</span>
  @endif

  @if ($product->isDepositBilled())
    <span class="mx-eyebrow mb-2" style="font-size:10.5px"><i class="fa-solid fa-bolt"></i> Bayar per jam</span>
  @endif

  <h3 style="font-size:1.15rem;font-weight:700;letter-spacing:-.02em;color:#18181b;margin:0 0 .35rem;padding-right:5rem">{{ $product->name }}</h3>
  @if ($product->tagline)
    <p style="font-size:13.5px;color:#71717a;margin:0;line-height:1.6">{{ $product->tagline }}</p>
  @endif

  @if ($product->features)
    <ul class="mx-feat">
      @foreach (array_slice($product->features, 0, 4) as $feature)
        <li><i class="fa-solid fa-check"></i><span>{{ $feature }}</span></li>
      @endforeach
    </ul>
  @else
    <div style="flex-grow:1;min-height:1rem"></div>
  @endif

  <div style="border-top:1px solid #f0efe9;padding-top:1.25rem">
    @if ($product->isDepositBilled())
      @php $hourly = $product->estimatedHourlyRate(); @endphp
      @if ($hourly)
        <div class="mx-price">Rp {{ number_format($hourly, 2, ',', '.') }} <small>/ jam</small></div>
        <p style="font-size:11.5px;color:#71717a;margin:.4rem 0 0">± Rp {{ number_format($hourly * 730, 0, ',', '.') }} / bulan bila menyala terus · dipotong dari saldo</p>
      @else
        <div class="mx-price" style="font-size:1.3rem">Sesuai Pemakaian</div>
        <p style="font-size:11.5px;color:#71717a;margin:.4rem 0 0">Dipotong otomatis dari saldo, per jam</p>
      @endif
    @elseif ($product->starting_price !== null)
      <div class="mx-price">Rp {{ number_format($product->starting_price, 0, ',', '.') }} <small>{{ $unit[$firstCycleKey] ?? '' }}</small></div>
    @else
      <p class="text-danger mb-0" style="font-size:14px">Harga belum tersedia</p>
    @endif
    <span class="btn {{ $product->isInStock() ? 'btn-theme' : 'btn-outline-secondary' }} w-100 mt-3" style="{{ $product->isInStock() ? '' : 'pointer-events:none;opacity:.6' }}">
      {{ $product->isInStock() ? 'Lihat Detail' : 'Stok Habis' }}
    </span>
  </div>
</a>
