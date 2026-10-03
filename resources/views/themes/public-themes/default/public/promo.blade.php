@extends('public.layout')

@php
  $seoTitle       = 'Promo & Diskon';
  $seoDescription = 'Kode promo dan diskon domain & hosting. Salin kodenya, lalu masukkan saat checkout.';
  $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
  $count = $promos->count();
@endphp

@section('content')
  <style>
    .pr-hero{background:linear-gradient(135deg,#1e1b4b 0%,#4338ca 55%,#6366f1 100%);color:#fff;border-radius:1.25rem;padding:2.25rem 1.75rem;margin-bottom:1.5rem;position:relative;overflow:hidden}
    .pr-hero:after{content:"";position:absolute;right:-60px;top:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.08)}
    .pr-hero h1{font-size:1.9rem;font-weight:800;margin:0 0 .4rem}
    .pr-hero p{margin:0;opacity:.85;max-width:38rem}
    .pr-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem}
    .pr-tab{border:1px solid #d1d5db;background:#fff;color:#374151;border-radius:999px;padding:.4rem 1rem;font-size:.85rem;font-weight:600;cursor:pointer;transition:.15s}
    .pr-tab:hover{background:#f3f4f6}
    .pr-tab.on{background:#4338ca;border-color:#4338ca;color:#fff}
    .pr-card{background:#fff;border:1px solid #e5e7eb;border-radius:1.1rem;overflow:hidden;height:100%;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s}
    .pr-card:hover{box-shadow:0 12px 30px rgba(67,56,202,.13);transform:translateY(-2px)}
    .pr-top{padding:1.1rem 1.25rem;color:#fff;position:relative;background:linear-gradient(135deg,#4f46e5,#7c3aed)}
    .pr-top.domain{background:linear-gradient(135deg,#0891b2,#4f46e5)}
    .pr-top.hosting{background:linear-gradient(135deg,#7c3aed,#db2777)}
    .pr-off{font-size:2rem;font-weight:800;line-height:1}
    .pr-off small{font-size:.8rem;font-weight:600;opacity:.85;margin-left:.25rem}
    .pr-kind{position:absolute;right:1rem;top:1rem;background:rgba(255,255,255,.2);border-radius:999px;padding:.15rem .65rem;font-size:.7rem;font-weight:600}
    .pr-body{padding:1.1rem 1.25rem;display:flex;flex-direction:column;flex:1}
    .pr-title{font-size:1.05rem;font-weight:700;margin:0 0 .35rem;color:#111827}
    .pr-desc{font-size:.87rem;color:#6b7280;margin:0 0 .85rem}
    .pr-count{display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:#6b7280;margin-bottom:.9rem;flex-wrap:wrap}
    .pr-count b{color:#b45309;font-weight:700}
    .pr-count .soon{color:#dc2626}
    .pr-code{display:flex;border:1.5px dashed #a5b4fc;border-radius:.7rem;overflow:hidden;background:#eef2ff;margin-top:auto}
    .pr-code code{flex:1;text-align:center;font-weight:800;letter-spacing:.06em;color:#3730a3;padding:.6rem .5rem;font-size:.95rem}
    .pr-code button{border:0;background:#4338ca;color:#fff;font-weight:600;font-size:.85rem;padding:0 1.1rem;cursor:pointer}
    .pr-code button:hover{background:#3730a3}
    .pr-more{border:0;background:none;color:#4338ca;font-weight:600;font-size:.85rem;padding:.75rem 0 0;text-align:left;cursor:pointer}
    .pr-detail{display:none;border-top:1px solid #f0f0f5;margin-top:.75rem;padding-top:.75rem}
    .pr-detail.open{display:block}
    .pr-detail h4{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin:0 0 .4rem}
    .pr-tag{display:inline-block;background:#f3f4f6;border-radius:.4rem;padding:.15rem .5rem;font-size:.78rem;margin:0 .25rem .25rem 0}
    .pr-tag s{color:#9ca3af}
    .pr-tag em{font-style:normal;color:#059669;font-weight:700}
    .pr-terms{list-style:none;padding:0;margin:.6rem 0 0;font-size:.8rem;color:#6b7280}
    .pr-terms li{padding:.1rem 0}
    .pr-terms li:before{content:"✓ ";color:#059669;font-weight:700}
    .pr-faq details{border:1px solid #e5e7eb;border-radius:.8rem;background:#fff;margin-bottom:.6rem;padding:.85rem 1.1rem}
    .pr-faq summary{font-weight:600;cursor:pointer}
    .pr-faq p{margin:.6rem 0 0;color:#6b7280;font-size:.9rem}
    .pr-cta{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:1.1rem;padding:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
  </style>

  @include('public._promo-banner-carousel')

  <section class="pr-hero">
    <h1>Promo &amp; Diskon</h1>
    <p>Kumpulan promo domain dan hosting yang sedang berlangsung. Salin kode promonya, lalu masukkan pada kolom kupon saat checkout. Diskon tidak diterapkan otomatis.</p>
  </section>

  @if ($count > 0)
    <div class="pr-tabs" role="tablist" aria-label="Filter promo">
      <button type="button" class="pr-tab on" data-filter="all">Semua ({{ $count }})</button>
      <button type="button" class="pr-tab" data-filter="domain">Domain</button>
      <button type="button" class="pr-tab" data-filter="hosting">Hosting &amp; Produk</button>
    </div>
  @endif

  <div class="row g-4 mb-5" id="promoGrid">
    @forelse ($promos as $promo)
      @php
        $c = $promo['coupon'];
        $quota = $c->remainingQuota();
        $kind = in_array('domain', $promo['kinds'], true) && ! in_array('hosting', $promo['kinds'], true) ? 'domain'
              : (in_array('hosting', $promo['kinds'], true) && ! in_array('domain', $promo['kinds'], true) ? 'hosting' : 'all');
        $kindLabel = ['domain' => 'Domain', 'hosting' => 'Hosting', 'all' => 'Semua Produk'][$kind];
      @endphp
      <div class="col-md-6 col-xl-4" data-kinds="{{ implode(' ', $promo['kinds']) }}">
        <article class="pr-card">
          <div class="pr-top {{ $kind }}">
            <span class="pr-kind">{{ $kindLabel }}</span>
            <div class="pr-off">Diskon {{ $c->value_label }}</div>
          </div>

          <div class="pr-body">
            <h2 class="pr-title">{{ $c->title ?: 'Kode ' . $c->code }}</h2>
            @if ($c->description)
              <p class="pr-desc">{!! nl2br(e($c->description)) !!}</p>
            @endif

            <div class="pr-count">
              @if ($c->expires_at)
                <span>Berlaku hingga</span>
                <b data-countdown="{{ $c->expires_at->copy()->endOfDay()->toIso8601String() }}">{{ $c->expires_at->format('d M Y') }}</b>
              @else
                <span>Berlaku tanpa batas waktu</span>
              @endif
            </div>

            <div class="pr-code">
              <code id="promoCodeText{{ $c->id }}">{{ $c->code }}</code>
              <input type="hidden" id="promoCode{{ $c->id }}" value="{{ $c->code }}">
              <button type="button" data-action="copy" data-target="promoCode{{ $c->id }}" data-promo-copy="{{ $c->code }}">Salin</button>
            </div>

            <button type="button" class="pr-more" data-promo-more aria-expanded="false">Lihat detail ▾</button>

            <div class="pr-detail">
              <h4>Berlaku untuk</h4>
              @if ($promo['all'])
                <span class="pr-tag">Semua produk &amp; domain</span>
              @else
                @foreach ($promo['tlds'] as $t)
                  <span class="pr-tag">
                    <strong>{{ $t['extension'] }}</strong>
                    @if ($t['after'] !== null && $t['after'] < $t['before'])
                      <s class="text-decoration-line-through text-muted ms-1">{{ $rp($t['before']) }}</s> <em>{{ $rp($t['after']) }}</em>
                    @else
                      {{ $rp($t['before']) }}
                    @endif
                    <span class="text-muted">/{{ $t['years'] }} thn</span>
                  </span>
                @endforeach
                @foreach ($promo['categories'] as $name)<span class="pr-tag">{{ $name }}</span>@endforeach
                @foreach ($promo['products'] as $name)<span class="pr-tag">{{ $name }}</span>@endforeach
                @if (! empty($promo['tlds']))
                  <div class="text-muted mt-1" style="font-size:.75rem">Harga contoh registrasi baru; potongan dihitung saat checkout.</div>
                @endif
              @endif

              <ul class="pr-terms">
                @if ((float) $c->min_order > 0)<li>Minimal transaksi {{ $rp($c->min_order) }}</li>@endif
                @if ($c->max_discount !== null)<li>Potongan maksimal {{ $rp($c->max_discount) }}</li>@endif
                @if ($quota !== null)<li>Sisa kuota: {{ $quota }}</li>@endif
                <li>Maksimal {{ $c->usage_limit_per_client }}× pemakaian per akun</li>
                <li>Kode dimasukkan manual saat checkout</li>
              </ul>

              <div class="d-flex flex-wrap gap-2 mt-3">
                @if (in_array('domain', $promo['kinds'], true))
                  <a href="{{ route('domain.search', $promo['searchExtensions'] ? ['extensions' => $promo['searchExtensions']] : []) }}" class="btn btn-primary btn-sm">Cek domain</a>
                @endif
                @if (in_array('hosting', $promo['kinds'], true))
                  <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary btn-sm">Lihat paket hosting</a>
                @endif
              </div>
            </div>
          </div>
        </article>
      </div>
    @empty
      <div class="col-12">
        <div class="card border rounded-4 p-5 text-center text-muted">Belum ada promo aktif saat ini. Silakan cek lagi nanti.</div>
      </div>
    @endforelse
  </div>

  <div id="promoEmptyFilter" class="card border rounded-4 p-4 text-center text-muted mb-5" style="display:none">Belum ada promo untuk kategori ini.</div>

  {{-- CTA --}}
  <div class="pr-cta mb-5">
    <div>
      <div class="fw-bold" style="font-size:1.1rem">Cari nama domain untuk website Anda</div>
      <div class="text-muted small">Cek ketersediaan domain, lalu pakai kode promo saat checkout.</div>
    </div>
    <a href="{{ route('domain.search') }}" class="btn btn-primary">Cek Domain Sekarang</a>
  </div>

  {{-- FAQ --}}
  <section class="pr-faq mb-4">
    <h2 class="fw-bold mb-3" style="font-size:1.25rem">Seputar Promo</h2>
    <details>
      <summary>Bagaimana cara menggunakan kode promo?</summary>
      <p>Salin kode dari kartu promo, tambahkan produk atau domain ke keranjang, lalu tempel kode pada kolom kupon di halaman checkout dan klik terapkan.</p>
    </details>
    <details>
      <summary>Apakah diskon diterapkan otomatis?</summary>
      <p>Tidak. Kode promo harus dimasukkan sendiri saat checkout supaya diskon yang berlaku sesuai dengan promo yang Anda pilih.</p>
    </details>
    <details>
      <summary>Bagaimana ketentuan promo?</summary>
      <p>Setiap promo punya syarat sendiri, seperti minimal transaksi, batas potongan, kuota, dan masa berlaku. Klik "Lihat detail" pada kartu promo untuk membacanya.</p>
    </details>
    <details>
      <summary>Apakah promo domain berlaku untuk perpanjangan?</summary>
      <p>Kupon domain berlaku untuk registrasi baru. Harga add-on seperti ID Protection tidak ikut didiskon.</p>
    </details>
  </section>

  <script @nonce>
    (function () {
      // Filter tab
      var tabs = document.querySelectorAll('.pr-tab');
      var cols = document.querySelectorAll('#promoGrid [data-kinds]');
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var f = tab.getAttribute('data-filter'), shown = 0;
          tabs.forEach(function (t) { t.classList.toggle('on', t === tab); });
          cols.forEach(function (col) {
            var ok = f === 'all' || col.getAttribute('data-kinds').split(' ').indexOf(f) !== -1;
            col.style.display = ok ? '' : 'none';
            if (ok) { shown++; }
          });
          document.getElementById('promoEmptyFilter').style.display = shown ? 'none' : '';
        });
      });

      // Detail
      document.addEventListener('click', function (e) {
        var more = e.target.closest && e.target.closest('[data-promo-more]');
        if (more) {
          var box = more.parentNode.querySelector('.pr-detail');
          var open = box.classList.toggle('open');
          more.setAttribute('aria-expanded', open ? 'true' : 'false');
          more.textContent = open ? 'Sembunyikan ▴' : 'Lihat detail ▾';
          return;
        }

        var btn = e.target.closest && e.target.closest('[data-promo-copy]');
        if (!btn) { return; }
        var code = btn.getAttribute('data-promo-copy'), old = btn.textContent;
        var done = function () { btn.textContent = 'Tersalin'; setTimeout(function () { btn.textContent = old; }, 1500); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(code).then(done, done);
        } else {
          var ta = document.createElement('textarea'); ta.value = code; document.body.appendChild(ta);
          ta.select(); try { document.execCommand('copy'); } catch (err) {} ta.remove(); done();
        }
      });

      // Hitung mundur
      function tick() {
        document.querySelectorAll('[data-countdown]').forEach(function (el) {
          var ms = new Date(el.getAttribute('data-countdown')).getTime() - Date.now();
          if (isNaN(ms)) { return; }
          if (ms <= 0) { el.textContent = 'Berakhir'; return; }
          var d = Math.floor(ms / 864e5), h = Math.floor(ms % 864e5 / 36e5), m = Math.floor(ms % 36e5 / 6e4);
          el.textContent = d + ' hari ' + h + ' jam ' + m + ' menit';
          el.classList.toggle('soon', d < 3);
        });
      }
      tick(); setInterval(tick, 30000);
    })();
  </script>
@endsection
