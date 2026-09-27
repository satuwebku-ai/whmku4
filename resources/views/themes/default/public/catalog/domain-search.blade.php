@extends('public.layout')

@php
  use App\Models\Setting;
  $themeColor = Setting::get('theme_color', '#6366F1');

  $seoTitle = 'Cek Ketersediaan Domain';
  $seoDescription = 'Cari dan daftarkan domain impian Anda — proses cepat, harga transparan.';

  $availableCount = ($results && $results['success'])
    ? collect($results['results'])->filter(fn ($v) => $v === true)->count()
    : 0;

  $unknownCount = ($results && $results['success'])
    ? count($results['unknown'] ?? [])
    : 0;
@endphp

@section('full-width')

  {{-- ══════════ Kotak pencarian ══════════ --}}
  <section class="position-relative overflow-hidden">
    <div class="position-absolute top-0 start-0 end-0 bottom-0" style="background:linear-gradient(160deg,#1e1b4b 0%,#312e81 40%,#4c1d95 75%,#1e1b4b 100%)"></div>

    <div class="position-relative container text-center py-5" style="max-width:48rem">
      <p class="text-white mb-2" style="opacity:.5;font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase">Daftar Domain Baru</p>
      <h1 class="fw-bold text-white mb-4" style="font-size:1.9rem">
        Domain <span style="color:#fcd34d">keren</span> bikin websitemu gampang diingat
      </h1>

      <form method="GET" action="{{ route('domain.search') }}" id="searchForm"
            class="bg-white rounded-4 p-2 d-flex flex-column flex-sm-row gap-2 shadow">
        <div class="d-flex align-items-center gap-2 flex-grow-1 px-3">
          <i class="fa-solid fa-globe text-muted"></i>
          <input type="text" name="domain" value="{{ $query }}"
                 placeholder="ketik nama domain yang kamu inginkan…"
                 class="w-100 py-2 border-0" style="outline:none;font-size:14px" required autofocus>
        </div>

        {{-- Ekstensi terpilih ikut terkirim dari panel di bawah --}}
        <div id="selectedMirror"></div>

        <button type="submit" class="btn btn-theme flex-shrink-0 py-2 px-4">
          <i class="fa-solid fa-magnifying-glass" style="font-size:12px"></i> Cari Domain
        </button>
      </form>

      <p class="text-white mt-3 mb-0" style="opacity:.4;font-size:12px">
        Sudah punya domain di tempat lain?
        <a href="{{ route('domains.transfer') }}" class="text-white text-decoration-underline" style="opacity:.7">Transfer ke sini</a>
      </p>
    </div>
  </section>

  {{-- ══════════ Banner Promo ══════════ --}}
  <div class="container mt-4" style="max-width:72rem">
    @include('public._promo-banner-carousel')
  </div>

  <div class="container py-5" style="max-width:72rem">
    <div class="row g-4">

      {{-- ══════════ Sidebar kategori ══════════ --}}
      <div class="col-12 col-lg-4 d-flex flex-column gap-3">
        <div class="card-public overflow-hidden">
          <div class="px-3 py-3 border-bottom">
            <p class="fw-semibold text-dark mb-0" style="font-size:14px">Kategori</p>
            <p class="text-muted mb-0" style="font-size:11px">Klik untuk memilih sekelompok ekstensi</p>
          </div>
          <div>
            @forelse ($groups as $groupName => $tlds)
              <button type="button" data-group="{{ $groupName }}"
                      class="w-100 btn d-flex align-items-center justify-content-between px-3 py-2 text-muted border-0 border-bottom text-start" style="font-size:14px">
                {{ $groupName }}
                <span class="text-muted" style="font-size:12px">{{ $tlds->count() }}</span>
              </button>
            @empty
              <p class="px-3 py-3 text-muted mb-0" style="font-size:12px">Belum ada ekstensi.</p>
            @endforelse
          </div>
        </div>

        <div class="card-public p-3">
          <p class="text-muted mb-0" style="font-size:12px;line-height:1.6">
            <i class="fa-solid fa-circle-info text-theme"></i>
            Biarkan kosong untuk mencari otomatis di semua ekstensi yang kami jual
            (maksimal 20 hasil), atau centang ekstensi tertentu untuk hasil yang lebih spesifik.
          </p>
        </div>
      </div>

      {{-- ══════════ Ekstensi & hasil ══════════ --}}
      <div class="col-12 col-lg-8 d-flex flex-column gap-4">

        {{-- Pilih ekstensi --}}
        @if ($tldPrices->isNotEmpty())
          <div class="card-public overflow-hidden">
            <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <p class="fw-semibold text-dark mb-0" style="font-size:14px">Ekstensi Domain</p>
                <p class="text-muted mb-0" style="font-size:11px">
                  <span id="selCount">0</span> dipilih —
                  <span class="text-muted">kosongkan untuk mencari otomatis di semua ekstensi</span>
                </p>
              </div>
              <div class="d-flex align-items-center gap-3" style="font-size:12px">
                <button type="button" id="selAll" class="btn btn-link p-0 text-theme" style="text-decoration:underline">Pilih semua</button>
                <button type="button" id="selNone" class="btn btn-link p-0 text-muted" style="text-decoration:underline">Kosongkan</button>
              </div>
            </div>

            <div class="p-4 row g-2">
              @foreach ($tldPrices as $ext => $tld)
                <div class="col-6 col-sm-4">
                  <label data-ext-group="{{ $tld->search_group_label }}"
                         class="d-flex align-items-center justify-content-between gap-2 rounded-3 border px-3 py-2 h-100" style="cursor:pointer">
                    <span class="d-flex align-items-center gap-2 min-w-0">
                      <input type="checkbox" value="{{ $ext }}" data-ext
                             @checked(in_array($ext, $selected))
                             class="form-check-input flex-shrink-0" style="margin:0">
                      <span class="text-dark text-truncate" style="font-size:14px">{{ $ext }}</span>
                      @if ($tld->is_demo)
                        <span class="badge flex-shrink-0" style="font-size:9px;background:#fef3c7;color:#b45309">DEMO</span>
                      @endif
                    </span>
                    <span class="text-muted flex-shrink-0" style="font-size:10px">
                      {{ $tld->register_price >= 1000000
                          ? 'Rp' . number_format($tld->register_price / 1000000, 1) . 'jt'
                          : 'Rp' . number_format($tld->register_price / 1000, 0) . 'rb' }}
                    </span>
                  </label>
                </div>
              @endforeach
            </div>
          </div>
        @else
          <div class="card-public p-5 text-center">
            <p class="text-muted mb-1" style="font-size:14px">Belum ada ekstensi yang ditampilkan.</p>
            <p class="text-muted mb-0" style="font-size:12px">
              Atur lewat admin: <b>Domain → TLD Pricing</b>, centang kolom "Tampil di Web".
            </p>
          </div>
        @endif

        {{-- Hasil pencarian --}}
        @if ($query)
          <div>
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
              <h2 class="fw-semibold text-dark mb-0" style="font-size:16px">Hasil pencarian untuk "{{ $query }}"</h2>
              <div class="d-flex align-items-center gap-3" style="font-size:12px">
                @if ($results && $results['success'] && $availableCount > 0)
                  <span class="text-success fw-medium">{{ $availableCount }} tersedia</span>
                @endif
                @if ($unknownCount > 0)
                  <span style="color:#b45309">{{ $unknownCount }} belum pasti</span>
                @endif
              </div>
            </div>

            @if (! $results['success'])
              <div class="card-public p-4 text-center">
                <p class="mb-1" style="font-size:14px;color:#e11d48">
                  <i class="fa-solid fa-circle-exclamation"></i> {{ $results['message'] }}
                </p>
                <p class="text-muted mb-0" style="font-size:12px">Coba pilih lebih sedikit ekstensi, atau ulangi beberapa saat lagi.</p>
              </div>
            @else
              <div class="d-flex flex-column gap-2">
                @forelse ($results['results'] as $domainName => $available)
                  @php
                    $ext = '.' . \Illuminate\Support\Str::after($domainName, '.');
                    $tld = $allTldPrices->get($ext);
                    $rowStyle = $available === true ? 'border-color:#a7f3d0!important;background:rgba(16,185,129,.04)'
                              : ($available === null ? 'border-color:#fde68a!important;background:rgba(245,158,11,.04)'
                              : 'background:#f8fafc');
                  @endphp

                  <div class="rounded-3 border px-4 py-3 d-flex align-items-center justify-content-between gap-3 flex-wrap" style="{{ $rowStyle }}">
                    <div class="min-w-0">
                      <p class="fw-semibold mb-0" style="{{ $available === true ? 'color:#1e293b' : ($available === null ? 'color:#334155' : 'color:#94a3b8;text-decoration:line-through') }}">
                        {{ $domainName }}
                        @if ($tld?->is_demo)
                          <span class="badge ms-1" style="font-size:10px;background:#fef3c7;color:#b45309;vertical-align:middle">DEMO</span>
                        @endif
                      </p>
                      @if ($available === true && $tld)
                        <p class="text-muted mb-0" style="font-size:12px">
                          Rp {{ number_format($tld->register_price, 0, ',', '.') }} <span class="text-muted">/tahun</span>
                        </p>
                      @elseif ($available === null)
                        <p class="mb-0" style="font-size:12px;color:#b45309">Status belum bisa dipastikan — akan dicek ulang saat pemesanan.</p>
                      @endif
                    </div>

                    @if ($available === true)
                      <form method="POST" action="{{ route('domain.add-to-cart') }}" data-add-domain class="d-flex align-items-center gap-2 flex-shrink-0">
                        @csrf
                        <input type="hidden" name="domain_name" value="{{ $domainName }}">
                        <input type="hidden" name="tld_id" value="{{ $tld->id ?? '' }}">
                        <select name="years" class="form-select form-select-sm" style="width:auto">
                          @for ($y = 1; $y <= min($tld->max_years ?? 5, 5); $y++)
                            <option value="{{ $y }}">{{ $y }} thn</option>
                          @endfor
                        </select>
                        <button type="submit" class="btn btn-theme py-1 px-3" style="font-size:12px" {{ $tld ? '' : 'disabled' }}>
                          <i class="fa-solid fa-cart-plus" style="font-size:11px"></i> Tambah
                        </button>
                      </form>
                    @elseif ($available === null)
                      <span class="flex-shrink-0" style="font-size:12px;color:#b45309">Perlu Dicek Manual</span>
                    @else
                      <span class="text-muted flex-shrink-0" style="font-size:12px">Tidak Tersedia</span>
                    @endif
                  </div>
                @empty
                  <div class="card-public p-4 text-center text-muted" style="font-size:14px">Tidak ada hasil untuk pencarian ini.</div>
                @endforelse
              </div>
            @endif
          </div>

          {{-- Langkah pemesanan --}}
          <div class="card-public p-4 d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <a href="{{ route('home') }}" class="btn btn-outline-secondary">Kembali</a>

            <div class="d-flex align-items-center gap-2" style="font-size:12px">
              @foreach (['Domain', 'Hosting', 'Keranjang', 'Bayar'] as $i => $step)
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:24px;height:24px;font-size:11px;{{ $i === 0 ? 'color:#fff;background:' . $themeColor : 'background:#e2e8f0;color:#64748b' }}">{{ $i + 1 }}</span>
                  <span style="{{ $i === 0 ? 'font-weight:600;color:#1e293b' : 'color:#94a3b8' }}">{{ $step }}</span>
                  @if ($i < 3)
                    <span style="width:24px;height:1px;background:#e2e8f0"></span>
                  @endif
                </div>
              @endforeach
            </div>

            <a href="{{ route('catalog.index', ['dari_domain' => 1]) }}" class="btn btn-theme">
              Lanjut <i class="fa-solid fa-arrow-right" style="font-size:12px"></i>
            </a>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Badge keranjang mengambang — sengaja fixed (bukan ikut scroll di
       dalam daftar hasil), supaya tetap terlihat berapa domain yang sudah
       ditambahkan meski sedang scroll jauh ke bawah daftar hasil.
       Ditaruh kiri bawah supaya tidak tumpang tindih dengan widget
       live chat yang sudah ada di kanan bawah. --}}
  <div id="floatingCartBadge" class="position-fixed d-none" style="left:20px;bottom:20px;z-index:1050">
    <a href="{{ route('cart.index') }}" class="d-flex align-items-center gap-2 text-decoration-none rounded-pill shadow" style="background:#1e293b;color:#fff;padding:.75rem 1.25rem .75rem 1rem">
      <span class="position-relative">
        <i class="fa-solid fa-cart-shopping"></i>
        <span id="floatingCartCount" class="position-absolute rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="top:-8px;right:-8px;width:16px;height:16px;font-size:9px;background:var(--lumora-theme)">0</span>
      </span>
      <span class="fw-medium" style="font-size:14px">Lihat Keranjang</span>
    </a>
  </div>

  <div id="toastBox" class="position-fixed d-none" style="left:20px;bottom:96px;z-index:1055;max-width:20rem">
    <div id="toastInner" class="d-flex align-items-center gap-2 rounded-3 px-3 py-2 shadow text-white" style="background:#059669;font-size:14px">
      <i class="fa-solid fa-circle-check flex-shrink-0"></i>
      <span id="toastMsg"></span>
    </div>
  </div>

  <script>
    (function () {
      const boxes  = Array.from(document.querySelectorAll('[data-ext]'));
      const mirror = document.getElementById('selectedMirror');
      const count  = document.getElementById('selCount');

      // Checkbox berada di luar <form> pencarian (beda kolom layout), jadi
      // pilihannya disalin jadi hidden input tepat sebelum submit.
      function sync() {
        mirror.innerHTML = '';
        let n = 0;

        boxes.forEach(function (b) {
          if (!b.checked) return;
          n++;
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'extensions[]';
          hidden.value = b.value;
          mirror.appendChild(hidden);
        });

        count.textContent = n;
      }

      boxes.forEach(b => b.addEventListener('change', sync));

      document.getElementById('selAll')?.addEventListener('click', function () {
        boxes.forEach(b => b.checked = true); sync();
      });
      document.getElementById('selNone')?.addEventListener('click', function () {
        boxes.forEach(b => b.checked = false); sync();
      });

      // Klik kategori → centang hanya ekstensi dalam kelompok itu.
      document.querySelectorAll('[data-group]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const group = btn.dataset.group;
          boxes.forEach(function (b) {
            b.checked = b.closest('[data-ext-group]')?.dataset.extGroup === group;
          });
          sync();
        });
      });

      sync();
    })();

    (function () {
      const badge      = document.getElementById('floatingCartBadge');
      const badgeCount = document.getElementById('floatingCartCount');
      const topbarBadge = document.getElementById('cartBadge');
      const toast      = document.getElementById('toastBox');
      const toastInner = document.getElementById('toastInner');
      const toastMsg   = document.getElementById('toastMsg');
      let toastTimer = null;

      function updateCartCount(n) {
        badgeCount.textContent = n;
        badge.classList.toggle('d-none', n <= 0);

        if (topbarBadge) {
          topbarBadge.textContent = n;
          topbarBadge.classList.toggle('d-none', n <= 0);
        }
      }

      function showToast(message, isError) {
        toastMsg.textContent = message;
        toastInner.style.background = isError ? '#e11d48' : '#059669';
        toast.classList.remove('d-none');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.add('d-none'), 3500);
      }

      // Mulai dari jumlah yang sudah dirender server di topbar, supaya
      // badge mengambang langsung akurat kalau keranjang sudah berisi
      // sesuatu dari kunjungan sebelumnya.
      updateCartCount(parseInt(topbarBadge?.textContent || '0', 10));

      document.querySelectorAll('form[data-add-domain]').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
          e.preventDefault();

          const btn = form.querySelector('button[type="submit"]');
          const originalHtml = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:11px"></i>';

          try {
            const res = await fetch(form.action, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
              },
              body: new FormData(form),
            });
            const data = await res.json();

            showToast(data.message, !data.success);

            if (data.success) {
              updateCartCount(data.cart_count);
              btn.innerHTML = '<i class="fa-solid fa-check" style="font-size:11px"></i> Ditambahkan';
              // Tombol dikembalikan normal setelah sebentar — klien tetap
              // bisa menambah domain lain kapan saja, tidak terkunci.
              setTimeout(function () {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
              }, 1500);
            } else {
              btn.innerHTML = originalHtml;
              btn.disabled = false;
            }
          } catch (err) {
            showToast('Gagal menambah domain. Periksa koneksi Anda dan coba lagi.', true);
            btn.innerHTML = originalHtml;
            btn.disabled = false;
          }
        });
      });
    })();
  </script>

@endsection
