@extends('client.layout')
@section('title', 'Addons — ' . $service->domain)

@section('content')
  <a href="{{ route('client.services.show', $service) }}" class="text-decoration-none text-muted" style="font-size:12px">
    &larr; Kembali ke {{ $service->domain }}
  </a>

  <div class="mt-2 mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Addons — {{ $service->domain }}</h1>
    <p class="text-muted mb-0">Fitur tambahan yang bisa dipasang di layanan hosting ini.</p>
  </div>

  @if ($attached->isNotEmpty())
    <div class="card-public overflow-hidden mb-4">
      <div class="px-4 py-3 border-bottom">
        <h2 class="small fw-bold text-dark mb-0">Addon Terpasang</h2>
      </div>
      <div>
        @foreach ($attached->whereIn('status', ['pending_payment', 'active']) as $item)
          <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
            <div>
              <p class="text-dark mb-0" style="font-size:14px">{{ $item->name }}</p>
              <p class="text-muted mb-0" style="font-size:11px">
                Rp {{ number_format($item->price, 0, ',', '.') }} / {{ $service->billing_cycle === 'monthly' ? 'bulan' : $service->billing_cycle }}
                @if ($item->status === 'pending_payment')
                  — <span style="color:#b45309">menunggu pembayaran</span>
                @endif
              </p>
            </div>
            <div class="d-flex align-items-center gap-2">
              @if ($item->status === 'pending_payment' && $item->invoice)
                <a href="{{ route('client.invoices.show', $item->invoice) }}" class="btn btn-theme btn-sm">Bayar</a>
              @else
                <span class="badge badge-soft-success">Aktif</span>
              @endif
              <form method="POST" action="{{ route('client.services.addons.cancel', $item) }}"
                    data-confirm="Hentikan addon {{ $item->name }}? Tidak akan ikut ditagih lagi di perpanjangan berikutnya." data-confirm-title="Hentikan Addon" data-confirm-style="danger" data-confirm-label="Ya, Hentikan">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;padding:0">
                  <i class="fa-regular fa-trash-can" style="font-size:11px"></i>
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @if ($available->isEmpty())
    <div class="card-public p-5 text-center">
      <i class="fa-solid fa-puzzle-piece text-muted mb-3" style="font-size:1.75rem"></i>
      <p class="fw-medium text-dark mb-1">Belum ada addon tersedia</p>
      <p class="text-muted mb-0" style="font-size:14px">Semua addon yang ada sudah terpasang, atau belum ada addon untuk siklus tagihan layanan Anda.</p>
    </div>
  @else
    <div class="row g-3">
      @foreach ($available as $addon)
        @php
          $cyclePrice = $addon->priceForCycle($service->billing_cycle);
          $prorated = $service->prorateAddon($addon);
        @endphp
        <div class="col-sm-6 col-lg-4">
          <div class="card-public p-4 d-flex flex-column h-100">
            <h2 class="fw-semibold text-dark mb-1" style="font-size:15px">{{ $addon->name }}</h2>
            @if ($addon->description)
              <p class="text-muted mb-3 flex-grow-1" style="font-size:12px">{{ $addon->description }}</p>
            @endif

            <div class="pt-3 border-top mt-auto">
              <p class="text-dark mb-0" style="font-size:14px">
                Rp {{ number_format($cyclePrice, 0, ',', '.') }} / {{ $service->billing_cycle === 'monthly' ? 'bulan' : $service->billing_cycle }}
              </p>
              <p class="fw-medium text-theme mt-1 mb-0" style="font-size:12px">
                + Rp {{ number_format($prorated, 0, ',', '.') }} sekarang (prorata sisa siklus)
              </p>

              <form method="POST" action="{{ route('client.services.addons.request', $service) }}" class="mt-3">
                @csrf
                <input type="hidden" name="addon_id" value="{{ $addon->id }}">
                <button type="submit" class="btn btn-theme w-100">Pasang Addon Ini</button>
              </form>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
