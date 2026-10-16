{{--
  Langkah pemesanan: Keranjang -> Checkout -> Pembayaran.

  Parameter:
    $step  int  langkah yang sedang aktif (1, 2, atau 3)

  Pada langkah 2 (Checkout), langkah 1 menjadi tautan balik ke Keranjang; pada langkah 3
  keranjang sudah kosong, jadi langkah yang dilewati hanya berupa penanda. Gaya ada di
  partials/store-hero.blade.php (.store-steps) -- partial ini selalu dipakai di
  dalam header itu.
--}}
@php
  $steps = [
    1 => ['label' => 'Keranjang', 'url' => route('cart.index')],
    2 => ['label' => 'Checkout', 'url' => null],
    3 => ['label' => 'Pembayaran', 'url' => null],
  ];
@endphp
<nav aria-label="Langkah pemesanan">
  <ol class="store-steps">
    @foreach ($steps as $n => $s)
      @php
        $state = $n < $step ? 'is-done' : ($n === $step ? 'is-active' : '');
        $linked = $n < $step && $s['url'] && $step === 2;
      @endphp
      <li class="d-flex align-items-center gap-2">
        @if ($linked)
          <a href="{{ $s['url'] }}" class="store-step {{ $state }}">
        @else
          <span class="store-step {{ $state }}" @if ($n === $step) aria-current="step" @endif>
        @endif
          <span class="store-step__dot">
            @if ($n < $step)<i class="fa-solid fa-check" style="font-size:.7rem"></i>@else{{ $n }}@endif
          </span>
          {{ $s['label'] }}
        @if ($linked)
          </a>
        @else
          </span>
        @endif
        @unless ($loop->last)
          <span class="store-step__line {{ $n < $step ? 'is-done' : '' }}" aria-hidden="true"></span>
        @endunless
      </li>
    @endforeach
  </ol>
</nav>
