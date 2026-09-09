@extends('public.layout')

@php
  use Illuminate\Support\Str;
  $seoTitle = $product->name . ' — ' . $category->name;
  $seoDescription = $product->tagline ?: Str::limit(strip_tags((string) $product->description), 155);
  $cycles = $product->availableCycles();
  $unit = ['monthly' => '/bulan', 'quarterly' => '/3 bulan', 'semi_annually' => '/6 bulan', 'annually' => '/tahun'];
@endphp

@section('content')

  <nav class="text-muted mb-3" style="font-size:12px">
    <a href="{{ route('catalog.index') }}" class="text-decoration-none text-muted">Hosting</a> /
    <a href="{{ $category->publicUrl() }}" class="text-decoration-none text-muted">{{ $category->name }}</a> /
    {{ $product->name }}
  </nav>

  @if ($errors->any())
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#fef2f2;border:1px solid #fecaca;font-size:14px;color:#b91c1c">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <h1 class="fw-bold text-dark mb-1" style="font-size:1.6rem">{{ $product->name }}</h1>
      @if ($product->tagline)
        <p class="text-muted mb-4">{{ $product->tagline }}</p>
      @endif

      @if ($product->description)
        <div class="prose-content mb-4">{!! nl2br(e($product->description)) !!}</div>
      @endif

      @if ($product->features)
        <div class="card-public p-4">
          <h2 class="small fw-bold text-dark mb-3">Fitur yang Anda Dapatkan</h2>
          <div class="row g-2">
            @foreach ($product->features as $feature)
              <div class="col-sm-6">
                <div class="d-flex align-items-start gap-2 text-muted" style="font-size:14px">
                  <i class="fa-solid fa-circle-check text-success flex-shrink-0" style="width:14px;margin-top:3px;text-align:center"></i>
                  <span class="min-w-0">{{ $feature }}</span>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- Panel pemesanan --}}
    <div class="col-12 col-lg-4">
      <div class="card-public p-4" style="position:sticky;top:6rem">
        @if (! $product->isInStock())
          <div class="text-center py-4">
            <i class="fa-solid fa-box-open text-muted mb-3" style="font-size:2rem"></i>
            <p class="fw-semibold text-dark mb-1">Stok Habis</p>
            <p class="text-muted mb-0" style="font-size:14px">Paket ini sedang tidak tersedia untuk pemesanan baru.</p>
          </div>
        @else
        <form method="POST" action="{{ route('cart.add-product') }}" id="orderForm">
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->id }}">

          <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">Pilih Siklus Tagihan</p>
          <div class="mb-4">
            @foreach ($cycles as $cycleKey => $price)
              <label class="d-flex align-items-center justify-content-between p-3 rounded-3 border mb-2" style="cursor:pointer">
                <span class="d-flex align-items-center gap-2">
                  <input type="radio" name="billing_cycle" value="{{ $cycleKey }}" data-price="{{ $price }}" {{ $loop->first ? 'checked' : '' }} required class="cycle-radio" style="margin:0">
                  <span class="text-dark" style="font-size:14px">{{ $product->cycleLabel($cycleKey) }}</span>
                </span>
                <span class="fw-semibold text-dark" style="font-size:14px">Rp {{ number_format($price, 0, ',', '.') }}</span>
              </label>
            @endforeach
          </div>

          @if ($product->setup_fee > 0)
            <p class="text-muted mb-3" style="font-size:12px">+ Biaya setup Rp {{ number_format($product->setup_fee, 0, ',', '.') }} (sekali bayar)</p>
          @endif

          @if ($product->optionGroups->isNotEmpty())
            <div class="border-top pt-3 mb-3" id="optionsSection">
              @foreach ($product->optionGroups as $group)
                @continue($group->options->isEmpty())
                <div class="mb-3">
                  <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
                    {{ $group->name }}
                    @if ($group->isRadio() && $group->is_required)
                      <span class="text-danger">*</span>
                    @endif
                  </p>

                  @if ($group->isRadio() && ! $group->is_required)
                    <label class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size:13px;cursor:pointer">
                      <input type="radio" name="options[{{ $group->id }}]" value="" checked class="option-radio-none" data-group="{{ $group->id }}" style="margin:0">
                      Tidak perlu
                    </label>
                  @endif

                  @foreach ($group->options as $option)
                    <label class="d-flex align-items-center justify-content-between p-2 rounded-3 border mb-2 option-choice"
                           data-prices="{{ json_encode(['monthly' => $option->price_monthly, 'quarterly' => $option->price_quarterly, 'semi_annually' => $option->price_semi_annually, 'annually' => $option->price_annually, 'custom' => $option->price_custom]) }}">
                      <span class="d-flex align-items-center gap-2">
                        <input type="{{ $group->isRadio() ? 'radio' : 'checkbox' }}"
                               name="options[{{ $group->id }}]{{ $group->isRadio() ? '' : '[]' }}"
                               value="{{ $option->id }}"
                               class="option-input" data-group="{{ $group->id }}"
                               {{ $group->isRadio() && $group->is_required && $loop->first ? 'checked' : '' }}
                               style="margin:0">
                        <span class="text-dark" style="font-size:13px">{{ $option->name }}</span>
                      </span>
                      <span class="option-price fw-medium text-muted flex-shrink-0" style="font-size:12px"></span>
                    </label>
                  @endforeach
                </div>
              @endforeach
            </div>
          @endif

          <div class="d-flex align-items-center justify-content-between border-top pt-3 mb-3">
            <span class="text-muted" style="font-size:13px">Total per siklus</span>
            <span id="orderTotal" class="fw-bold text-dark" style="font-size:18px">Rp 0</span>
          </div>

          @if ($product->allowsDomain())
            <div class="border-top pt-3 mb-3">
              <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">
                Domain {{ $product->requiresDomain() ? '(Wajib)' : '(Opsional)' }}
              </p>

              <div>
                @unless ($product->requiresDomain())
                  <label class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size:14px;cursor:pointer">
                    <input type="radio" name="domain_mode" value="" checked class="domain-mode-radio" style="margin:0">
                    Tidak perlu domain
                  </label>
                @endunless
                <label class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size:14px;cursor:pointer">
                  <input type="radio" name="domain_mode" value="register" {{ $product->requiresDomain() ? 'checked' : '' }} class="domain-mode-radio" style="margin:0">
                  Daftarkan domain baru
                </label>
                <label class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size:14px;cursor:pointer">
                  <input type="radio" name="domain_mode" value="transfer" class="domain-mode-radio" style="margin:0">
                  Transfer domain dari registrar lain
                </label>
                <label class="d-flex align-items-center gap-2 text-muted mb-0" style="font-size:14px;cursor:pointer">
                  <input type="radio" name="domain_mode" value="existing" class="domain-mode-radio" style="margin:0">
                  Saya sudah punya domain ini (arahkan nameserver saja)
                </label>
              </div>

              <div id="domainNameField" class="mt-3 {{ $product->requiresDomain() ? '' : 'd-none' }}">
                <input type="text" name="domain_name" value="{{ old('domain_name') }}" placeholder="contoh.com" class="form-control form-control-sm">
                <p id="domainNameHint" class="text-muted mt-1 mb-0" style="font-size:11px">
                  Ketersediaan domain baru dicek ulang saat checkout.
                  Untuk cek dulu, pakai <a href="{{ route('domain.search') }}" class="text-theme" target="_blank">halaman Cek Domain</a>.
                </p>
              </div>

              <div id="transferAuthField" class="mt-3 d-none">
                <label class="fw-medium text-muted mb-1 d-block" style="font-size:12px">Kode EPP / Auth Code</label>
                <input type="text" name="transfer_auth_code" value="{{ old('transfer_auth_code') }}" placeholder="Diminta dari registrar domain Anda saat ini" class="form-control form-control-sm">
                <p class="text-muted mt-1 mb-0" style="font-size:11px">
                  Proses transfer butuh persetujuan pemilik domain (email dari registrar lama)
                  dan biasanya memakan waktu 5–7 hari, bukan langsung aktif detik itu juga.
                </p>
              </div>
            </div>
          @endif

          <button type="submit" class="btn btn-theme w-100">
            <i class="fa-solid fa-cart-plus" style="font-size:12px"></i> Tambah ke Keranjang
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>

  @if ($related->isNotEmpty())
    <div class="mt-5">
      <h2 class="fw-bold text-dark mb-3" style="font-size:1.15rem">Paket Lain di {{ $category->name }}</h2>
      <div class="row g-3">
        @foreach ($related as $rp)
          <div class="col-sm-6 col-lg-4">
            @include('public.catalog._product-card', ['product' => $rp])
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <script>
    (function () {
      const radios = document.querySelectorAll('.domain-mode-radio');
      const field = document.getElementById('domainNameField');
      const hint = document.getElementById('domainNameHint');
      const transferField = document.getElementById('transferAuthField');

      function syncDomainMode() {
        if (!radios.length || !field) return;
        const checked = document.querySelector('.domain-mode-radio:checked');
        const mode = checked ? checked.value : '';
        const needsInput = mode === 'register' || mode === 'transfer' || mode === 'existing';

        field.classList.toggle('d-none', !needsInput);
        field.querySelector('input').required = !!needsInput;

        transferField.classList.toggle('d-none', mode !== 'transfer');
        transferField.querySelector('input').required = mode === 'transfer';

        if (hint) {
          hint.textContent = mode === 'transfer'
            ? 'Domain harus sudah "unlock" di registrar lama sebelum bisa ditransfer.'
            : (mode === 'existing'
                ? 'Kami akan minta Anda mengarahkan nameserver domain ini setelah checkout.'
                : 'Ketersediaan domain baru dicek ulang saat checkout.');
        }
      }

      radios.forEach(r => r.addEventListener('change', syncDomainMode));

      // ── Opsi konfigurasi: sembunyikan opsi yang tidak dijual untuk
      // siklus tagihan aktif, tampilkan harganya, dan hitung total. ──
      const idr = (n) => 'Rp ' + Number(n).toLocaleString('id-ID');
      const cycleRadios = document.querySelectorAll('.cycle-radio');
      const optionChoices = document.querySelectorAll('.option-choice');
      const totalEl = document.getElementById('orderTotal');

      function currentCycle() {
        const c = document.querySelector('.cycle-radio:checked');
        return c ? c.value : null;
      }

      function syncOptionsForCycle() {
        const cycle = currentCycle();

        optionChoices.forEach((label) => {
          const prices = JSON.parse(label.dataset.prices || '{}');
          const price = prices[cycle];
          const input = label.querySelector('.option-input');
          const priceEl = label.querySelector('.option-price');
          const available = price !== null && price !== undefined;

          label.classList.toggle('d-none', !available);

          if (!available && input.checked) {
            input.checked = false;
          }

          if (available) {
            priceEl.textContent = Number(price) > 0 ? '+ ' + idr(price) : 'Gratis';
          }
        });
      }

      function updateTotal() {
        const cycleRadio = document.querySelector('.cycle-radio:checked');
        let total = cycleRadio ? Number(cycleRadio.dataset.price || 0) : 0;

        document.querySelectorAll('.option-input:checked').forEach((input) => {
          const label = input.closest('.option-choice');
          const prices = JSON.parse(label.dataset.prices || '{}');
          const price = prices[currentCycle()];
          if (price !== null && price !== undefined) total += Number(price);
        });

        if (totalEl) totalEl.textContent = idr(total);
      }

      function syncAll() {
        syncOptionsForCycle();
        updateTotal();
      }

      cycleRadios.forEach(r => r.addEventListener('change', syncAll));
      document.querySelectorAll('.option-input, .option-radio-none').forEach(el => el.addEventListener('change', updateTotal));

      syncDomainMode();
      syncAll();
    })();
  </script>

@endsection
