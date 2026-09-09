@extends('public.layout')

@php
  $seoTitle = 'Keranjang Belanja';
@endphp

@section('content')

  <h1 class="fw-bold text-dark mb-4" style="font-size:1.6rem">Keranjang Belanja</h1>

  <div class="row g-4">
    {{-- Sidebar: kategori & aksi cepat — tetap tampil meski keranjang kosong,
         supaya pengunjung bisa lanjut menjelajah tanpa jalan buntu. --}}
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">
      @if ($categories->isNotEmpty())
        <div class="card-public overflow-hidden">
          <div class="px-3 py-3 fw-semibold text-white" style="background:#1e293b;font-size:14px">Kategori Layanan</div>
          <div>
            @foreach ($categories as $category)
              <a href="{{ $category->publicUrl() }}" class="d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none text-muted border-bottom" style="font-size:14px">
                {{ $category->name }}
                <span class="text-muted" style="font-size:12px">{{ $category->products_count }}</span>
              </a>
            @endforeach
          </div>
        </div>
      @endif

      <div class="card-public overflow-hidden">
        <div class="px-3 py-3 fw-semibold text-white" style="background:#1e293b;font-size:14px">Aksi</div>
        <div>
          <a href="{{ route('domain.search') }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-muted border-bottom" style="font-size:14px">
            <i class="fa-solid fa-globe text-center" style="width:16px"></i> Daftarkan Domain Baru
          </a>
          <a href="{{ route('catalog.index') }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-muted border-bottom" style="font-size:14px">
            <i class="fa-solid fa-server text-center" style="width:16px"></i> Lihat Paket Hosting
          </a>
          <span class="d-flex align-items-center gap-2 px-3 py-2 fw-medium text-theme" style="font-size:14px;background:rgba(79,70,229,.05)">
            <i class="fa-solid fa-cart-shopping text-center" style="width:16px"></i> Keranjang ({{ count($items) }})
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      @if (empty($items))
        <div class="card-public p-5 text-center">
          <i class="fa-solid fa-cart-shopping text-muted mb-3" style="font-size:2rem"></i>
          <p class="text-muted mb-4">Keranjang Anda masih kosong.</p>
          <a href="{{ route('catalog.index') }}" class="btn btn-theme">Lihat Paket Hosting</a>
        </div>
      @else
        <div class="row g-4">
          <div class="col-12 col-lg-8 d-flex flex-column gap-3">
            @foreach ($items as $item)
              <div class="card-public p-4 d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3 min-w-0">
                  <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;{{ $item['type'] === 'domain' ? 'background:rgba(6,182,212,.14);color:#0891b2' : 'background:rgba(79,70,229,.12);color:#4f46e5' }}">
                    <i class="fa-solid {{ $item['type'] === 'domain' ? 'fa-globe' : 'fa-server' }}"></i>
                  </span>
                  <div class="min-w-0">
                    @if ($item['type'] === 'product')
                      <p class="fw-semibold text-dark mb-0">{{ $item['name'] }}</p>

                      <form method="POST" action="{{ route('cart.update-cycle') }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="key" value="{{ $item['key'] }}">
                        <select name="billing_cycle" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto">
                          @foreach (\App\Models\Product::CYCLES as $ck => $label)
                            <option value="{{ $ck }}" @selected($item['billing_cycle'] === $ck)>{{ $label }}</option>
                          @endforeach
                        </select>
                      </form>

                      @if (!empty($item['domain_name']))
                        <p class="text-muted mt-2 mb-0" style="font-size:11px">
                          <i class="fa-solid fa-globe" style="font-size:10px"></i>
                          {{ $item['domain_mode'] === 'register' ? 'Daftar baru' : 'Domain sendiri' }}: {{ $item['domain_name'] }}
                        </p>
                      @endif

                      @if (!empty($item['selected_options']))
                        <ul class="list-unstyled mt-2 mb-0 d-flex flex-column gap-1">
                          @foreach ($item['selected_options'] as $opt)
                            <li class="text-muted d-flex align-items-center gap-1" style="font-size:11px">
                              <i class="fa-solid fa-plus" style="font-size:9px"></i>
                              {{ $opt['name'] }}
                              <span class="text-dark fw-medium">— Rp {{ number_format($opt['price'], 0, ',', '.') }}</span>
                            </li>
                          @endforeach
                        </ul>
                      @endif
                    @else
                      <p class="fw-semibold text-dark mb-0">{{ $item['domain_name'] }}</p>
                      <form method="POST" action="{{ route('cart.update-years') }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="key" value="{{ $item['key'] }}">
                        <select name="years" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto">
                          @for ($y = 1; $y <= 10; $y++)
                            <option value="{{ $y }}" @selected($item['years'] == $y)>{{ $y }} Tahun</option>
                          @endfor
                        </select>
                      </form>

                      {{-- Add-on domain baru — dikemas jadi kartu kecil rapi,
                           bukan checkbox polos mengambang di antara teks. --}}
                      <div class="mt-3 rounded-3 border overflow-hidden">
                        {{-- DNS Management — informasional, selalu aktif untuk
                             domain baru (DNS bisa dikelola lewat panel setelah
                             domain aktif, tidak ada biaya tambahan). --}}
                        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="background:rgba(16,185,129,.05)">
                          <span class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;background:rgba(16,185,129,.14);color:#047857">
                            <i class="fa-solid fa-server" style="font-size:11px"></i>
                          </span>
                          <div class="flex-grow-1 min-w-0">
                            <p class="fw-medium text-dark mb-0" style="font-size:12px">DNS Management</p>
                            <p class="text-muted mb-0" style="font-size:11px">Kelola DNS lewat panel setelah domain aktif</p>
                          </div>
                          <span class="badge badge-soft-success flex-shrink-0" style="font-size:10px">Termasuk</span>
                        </div>

                        {{-- ID Protection (WHOIS Privacy) — hanya berlaku untuk
                             domain baru, bukan yang sudah dimiliki klien.
                             TLD di bawah .id dilarang PANDI menawarkan ini
                             sama sekali -- kartunya diganti keterangan
                             singkat, bukan checkbox yang kelihatan bisa
                             dicentang tapi diam-diam ditolak. --}}
                        @if ($item['whois_privacy_eligible'] ?? true)
                          <form method="POST" action="{{ route('cart.toggle-privacy') }}">
                            @csrf
                            <input type="hidden" name="key" value="{{ $item['key'] }}">
                            <label class="d-flex align-items-center gap-2 px-3 py-2" style="cursor:pointer">
                              <span class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;{{ ($item['whois_privacy'] ?? false) ? 'background:rgba(79,70,229,.12);color:#4f46e5' : 'background:#f1f5f9;color:#94a3b8' }}">
                                <i class="fa-solid fa-user-shield" style="font-size:11px"></i>
                              </span>
                              <div class="flex-grow-1 min-w-0">
                                <p class="fw-medium text-dark mb-0" style="font-size:12px">ID Protection</p>
                                <p class="text-muted mb-0" style="font-size:11px">Sembunyikan data pribadi dari WHOIS publik</p>
                              </div>
                              <span class="fw-medium flex-shrink-0" style="font-size:11px;{{ ($item['whois_privacy_price'] ?? 0) > 0 ? 'color:#64748b' : 'color:#047857' }}">
                                {{ ($item['whois_privacy_price'] ?? 0) > 0 ? '+Rp ' . number_format($item['whois_privacy_price'], 0, ',', '.') . '/thn' : 'Gratis' }}
                              </span>
                              <input type="checkbox" onchange="this.form.submit()" @checked($item['whois_privacy'] ?? false)
                                     class="form-check-input flex-shrink-0" style="margin:0">
                            </label>
                          </form>
                        @else
                          <div class="d-flex align-items-center gap-2 px-3 py-2">
                            <span class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;background:#f1f5f9;color:#94a3b8">
                              <i class="fa-solid fa-circle-info" style="font-size:11px"></i>
                            </span>
                            <div class="flex-grow-1 min-w-0">
                              <p class="fw-medium text-dark mb-0" style="font-size:12px">ID Protection tidak tersedia</p>
                              <p class="text-muted mb-0" style="font-size:11px">Domain .id dan turunannya wajib menampilkan data pendaftar sesuai aturan PANDI.</p>
                            </div>
                          </div>
                        @endif
                      </div>
                    @endif
                  </div>
                </div>

                <div class="text-end flex-shrink-0">
                  <p class="fw-semibold text-dark mb-0">Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                  @if (!empty($item['setup_fee']))
                    <p class="text-muted mb-0" style="font-size:11px">+ setup Rp {{ number_format($item['setup_fee'], 0, ',', '.') }}</p>
                  @endif
                  <form method="POST" action="{{ route('cart.remove') }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="key" value="{{ $item['key'] }}">
                    <button type="submit" class="btn btn-link p-0 text-danger" style="font-size:12px;text-decoration:none">
                      <i class="fa-regular fa-trash-can"></i> Hapus
                    </button>
                  </form>
                </div>
              </div>
            @endforeach

            <form method="POST" action="{{ route('cart.clear') }}"
                  data-confirm="Kosongkan seluruh keranjang?" data-confirm-title="Kosongkan Keranjang"
                  data-confirm-style="danger" data-confirm-label="Ya, Kosongkan">
              @csrf
              <button type="submit" class="btn btn-link p-0 text-muted" style="font-size:12px;text-decoration:none">
                <i class="fa-regular fa-trash-can"></i> Kosongkan keranjang
              </button>
            </form>
          </div>

          <div class="col-12 col-lg-4">
            <div class="card-public p-4" style="position:sticky;top:6rem">
              <h2 class="small fw-bold text-dark mb-3">Ringkasan</h2>
              <div class="d-flex justify-content-between mb-2" style="font-size:14px">
                <span class="text-muted">Subtotal</span>
                <span class="fw-medium text-dark">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
              </div>
              <p class="text-muted mb-3" style="font-size:11px">Biaya setup (jika ada) & pajak dihitung saat checkout.</p>

              <a href="{{ route('client.checkout') }}" class="btn btn-theme w-100">
                <i class="fa-solid fa-lock" style="font-size:12px"></i> Lanjut ke Checkout
              </a>
              <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary w-100 mt-2">Lanjut Belanja</a>
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>

@endsection
