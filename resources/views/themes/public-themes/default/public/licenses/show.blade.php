@extends('public.layout')

@php $seoTitle = $license->name; @endphp

@section('content')

  <div class="row g-4 py-4 py-lg-5">
    <div class="col-12 col-lg-7">
      <a href="{{ route('license.index') }}" class="small text-decoration-none text-theme"><i class="fa-solid fa-arrow-left"></i> Semua Lisensi & SSL</a>

      <div class="d-flex flex-wrap align-items-center gap-2 mt-4 mb-2">
        <span class="badge badge-soft-success">{{ $license->category_label }}</span>
        @if ($license->brand)<span class="badge badge-soft-secondary">{{ $license->brand }}</span>@endif
      </div>
      <h1 class="display-6 fw-bold text-dark">{{ $license->name }}</h1>
      @if ($license->summary)<p class="lead text-muted">{{ $license->summary }}</p>@endif

      <div class="prose-content text-muted mt-3">
        {!! nl2br(e($license->long_description ?: $license->description ?: 'Lisensi digital untuk kebutuhan operasional Anda.')) !!}
      </div>

      @if (! empty($license->features))
        <div class="card-public p-4 mt-4">
          <h2 class="h6 fw-bold text-dark mb-3">{{ $isSsl ? 'Keunggulan sertifikat ini' : 'Fitur utama' }}</h2>
          <ul class="list-unstyled mb-0">
            @foreach ($license->features as $feature)
              <li class="d-flex gap-2 mb-2 text-muted"><i class="fa-solid fa-circle-check text-theme mt-1"></i><span>{{ $feature }}</span></li>
            @endforeach
          </ul>
        </div>
      @endif

      @if (! empty($license->specs))
        <div class="card-public mt-4 overflow-hidden">
          <h2 class="h6 fw-bold text-dark p-4 pb-2 mb-0">Spesifikasi</h2>
          <div class="table-responsive">
            <table class="table table-borderless align-middle mb-0 small">
              <tbody>
                @foreach ($license->specs as $label => $value)
                  <tr class="border-top">
                    <th class="fw-semibold text-dark ps-4" style="width:40%">{{ $label }}</th>
                    <td class="text-muted pe-4">{{ $value }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif

      @if (! empty($license->faqs))
        <div class="mt-4">
          <h2 class="h6 fw-bold text-dark mb-3">Pertanyaan yang sering diajukan</h2>
          <div class="accordion" id="licenseFaq">
            @foreach ($license->faqs as $i => $faq)
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }} small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">{{ $faq['q'] }}</button>
                </h3>
                <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#licenseFaq">
                  <div class="accordion-body small text-muted">{{ $faq['a'] }}</div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    <div class="col-12 col-lg-5">
      <div class="card-public p-4 sticky-lg-top" style="top:6rem">
        <h2 class="h5 fw-bold text-dark">Pesan sekarang</h2>

        @if (count($cycles) === 1)
          @php($only = array_key_first($cycles))
          <div class="d-flex align-items-baseline gap-1 my-3">
            <span class="display-6 fw-bold text-dark">Rp {{ number_format($cycles[$only], 0, ',', '.') }}</span>
            <span class="text-muted">{{ $suffix[$only] ?? '' }}</span>
          </div>
          @if (! empty($license->specs['Registrasi & perpanjangan']))
            <p class="small text-muted">Registrasi dan perpanjangan: {{ strtolower($license->specs['Registrasi & perpanjangan']) }}.</p>
          @endif
        @else
          <p class="text-muted small">Pilih siklus pembayaran untuk melanjutkan.</p>
        @endif

        <form method="POST" action="{{ route('cart.add-addon') }}">
          @csrf
          <input type="hidden" name="addon_id" value="{{ $license->id }}">
          @if (count($cycles) === 1)
            <input type="hidden" name="billing_cycle" value="{{ $only }}">
          @else
            <label class="form-label small fw-semibold">Siklus pembayaran</label>
            <select name="billing_cycle" class="form-select mb-3" required>
              @foreach ($cycles as $cycle => $price)
                <option value="{{ $cycle }}">{{ $labels[$cycle] ?? $cycle }} - Rp {{ number_format($price, 0, ',', '.') }}</option>
              @endforeach
            </select>
          @endif
          <button class="btn btn-theme w-100"><i class="fa-solid fa-cart-plus me-1"></i> Tambahkan ke Keranjang</button>
        </form>

        <ul class="list-unstyled small text-muted mt-3 mb-0">
          <li class="mb-1"><i class="fa-solid fa-file-invoice me-2 text-theme"></i>Invoice dan status order tersimpan di panel client</li>
          <li><i class="fa-solid fa-headset me-2 text-theme"></i>Dukungan lewat tiket dan live chat</li>
        </ul>
      </div>
    </div>
  </div>

  @if ($related->isNotEmpty())
    <section class="pb-5">
      <h2 class="h5 fw-bold text-dark mb-3">{{ $isSsl ? 'Sertifikat SSL lainnya' : 'Lisensi lainnya' }}</h2>
      <div class="row g-3">
        @foreach ($related as $item)
          @php($cheap = $item->cheapestCycle())
          <div class="col-12 col-md-4">
            <a href="{{ route('license.show', $item->slug) }}" class="card-public d-block p-3 h-100 text-decoration-none">
              <div class="small text-muted">{{ $item->brand }}</div>
              <div class="fw-bold text-dark">{{ $item->name }}</div>
              <div class="text-theme fw-semibold mt-1">Rp {{ number_format($cheap['price'], 0, ',', '.') }} <span class="text-muted fw-normal small">{{ $suffix[$cheap['cycle']] ?? '' }}</span></div>
            </a>
          </div>
        @endforeach
      </div>
    </section>
  @endif
@endsection
