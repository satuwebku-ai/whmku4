@extends('public.layout')

@php
  $seoTitle       = 'Promo & Diskon';
  $seoDescription = 'Kode promo dan diskon domain & hosting. Salin kodenya, lalu masukkan saat checkout.';
  $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
@endphp

@section('content')
  @include('public._promo-banner-carousel')

  <div class="mb-4">
    <h1 class="fw-bold mb-1" style="font-size:1.6rem">Promo &amp; Diskon</h1>
    <p class="text-muted mb-0">Salin kode promo di bawah, lalu masukkan pada kolom kupon saat checkout. Diskon tidak diterapkan otomatis.</p>
  </div>

  <div class="row g-4">
    @forelse ($promos as $promo)
      @php
        $c = $promo['coupon'];
        $quota = $c->remainingQuota();
      @endphp
      <div class="col-md-6">
        <div class="card h-100 border rounded-4 shadow-sm">
          <div class="card-body p-4 d-flex flex-column">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <h2 class="fw-semibold mb-0" style="font-size:1.1rem">{{ $c->title ?: 'Kode ' . $c->code }}</h2>
              <span class="badge rounded-pill bg-success-subtle text-success-emphasis flex-shrink-0" style="font-size:.8rem">Diskon {{ $c->value_label }}</span>
            </div>

            @if ($c->description)
              <p class="text-muted mb-3" style="font-size:.9rem">{!! nl2br(e($c->description)) !!}</p>
            @endif

            {{-- Berlaku untuk --}}
            <div class="mb-3">
              <div class="small fw-medium mb-2">Berlaku untuk</div>
              @if ($promo['all'])
                <span class="badge text-bg-light border">Semua produk &amp; domain</span>
              @else
                <div class="d-flex flex-wrap gap-2">
                  @foreach ($promo['tlds'] as $t)
                    <span class="badge text-bg-light border fw-normal text-start" style="font-size:.8rem">
                      <strong>{{ $t['extension'] }}</strong>
                      @if ($t['after'] !== null && $t['after'] < $t['before'])
                        <span class="text-decoration-line-through text-muted ms-1">{{ $rp($t['before']) }}</span>
                        <span class="text-success ms-1">{{ $rp($t['after']) }}</span>
                      @else
                        <span class="text-muted ms-1">{{ $rp($t['before']) }}</span>
                      @endif
                      <span class="text-muted">/{{ $t['years'] }} thn</span>
                    </span>
                  @endforeach
                  @foreach ($promo['categories'] as $name)
                    <span class="badge text-bg-light border fw-normal" style="font-size:.8rem">{{ $name }}</span>
                  @endforeach
                  @foreach ($promo['products'] as $name)
                    <span class="badge text-bg-light border fw-normal" style="font-size:.8rem">{{ $name }}</span>
                  @endforeach
                </div>
                @if (! empty($promo['tlds']))
                  <div class="text-muted mt-2" style="font-size:.75rem">Harga contoh untuk registrasi baru; potongan dihitung saat checkout.</div>
                @endif
              @endif
            </div>

            {{-- Syarat singkat --}}
            <ul class="list-unstyled small text-muted mb-3">
              @if ((float) $c->min_order > 0)
                <li>Minimal transaksi {{ $rp($c->min_order) }}</li>
              @endif
              @if ($c->max_discount !== null)
                <li>Potongan maksimal {{ $rp($c->max_discount) }}</li>
              @endif
              @if ($c->expires_at)
                <li>Berlaku sampai {{ $c->expires_at->format('d M Y') }}</li>
              @endif
              @if ($quota !== null)
                <li>Sisa kuota: {{ $quota }}</li>
              @endif
              <li>Maksimal {{ $c->usage_limit_per_client }}× pemakaian per akun</li>
            </ul>

            {{-- Kode + aksi --}}
            <div class="mt-auto">
              <div class="input-group mb-2">
                <input type="text" id="promoCode{{ $c->id }}" class="form-control fw-bold text-center" value="{{ $c->code }}" readonly data-action="select" aria-label="Kode promo {{ $c->code }}">
                <button type="button" class="btn btn-outline-primary" data-action="copy" data-target="promoCode{{ $c->id }}" data-promo-copy>Salin</button>
              </div>
              @if ($promo['all'] || ! empty($promo['tlds']))
                <a href="{{ route('domain.search', $promo['searchExtensions'] ? ['extensions' => $promo['searchExtensions']] : []) }}" class="btn btn-primary w-100 mb-2">Cek domain</a>
              @endif
              @if ($promo['all'] || $promo['categories'] || $promo['products'])
                <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary w-100">Lihat paket hosting</a>
              @endif
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="card border rounded-4 p-5 text-center text-muted">Belum ada promo aktif saat ini. Silakan cek lagi nanti.</div>
      </div>
    @endforelse
  </div>

  <script @nonce>
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-promo-copy]');
      if (!btn) { return; }
      var old = btn.textContent;
      btn.textContent = 'Tersalin';
      setTimeout(function () { btn.textContent = old; }, 1500);
    });
  </script>
@endsection
