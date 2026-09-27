@extends('client.layout')
@section('title', 'Upgrade Paket — ' . $service->domain)

@section('content')
  <a href="{{ route('client.services.show', $service) }}" class="text-decoration-none text-muted" style="font-size:12px">
    &larr; Kembali ke {{ $service->domain }}
  </a>

  <div class="mt-2 mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Upgrade Paket</h1>
    <p class="text-muted mb-0">
      Paket saat ini: <b class="text-dark">{{ $service->product?->name ?? $service->package }}</b> —
      Rp {{ number_format((float) $service->price, 0, ',', '.') }} / {{ $service->billing_cycle === 'monthly' ? 'bulan' : $service->billing_cycle }}
    </p>
  </div>

  @if ($options->isEmpty())
    <div class="card-public p-5 text-center">
      <i class="fa-solid fa-circle-info text-muted mb-3" style="font-size:1.75rem"></i>
      <p class="fw-medium text-dark mb-1">Belum ada paket upgrade yang tersedia</p>
      <p class="text-muted mb-0" style="font-size:14px">Paket Anda mungkin sudah yang tertinggi, atau hubungi support untuk pilihan lain.</p>
    </div>
  @else
    <div class="row g-3">
      @foreach ($options as $option)
        @php
          $newPrice = $option->priceForCycle($service->billing_cycle);
          $prorated = $service->prorateUpgrade($option);
        @endphp
        <div class="col-sm-6 col-lg-4">
          <div class="card-public p-4 d-flex flex-column h-100">
            <h2 class="fw-semibold text-dark mb-1" style="font-size:15px">{{ $option->name }}</h2>
            <p class="text-muted mb-3" style="font-size:12px">{{ $option->tagline }}</p>

            @if ($option->features)
              <ul class="text-muted mb-3 flex-grow-1 ps-0" style="font-size:12px;list-style:none">
                @foreach (array_slice($option->features, 0, 4) as $feature)
                  <li class="mb-2"><i class="fa-solid fa-check text-success" style="font-size:10px"></i> {{ $feature }}</li>
                @endforeach
              </ul>
            @endif

            <div class="pt-3 border-top mt-auto">
              <p class="text-dark mb-0" style="font-size:14px">
                Rp {{ number_format($newPrice, 0, ',', '.') }} / {{ $service->billing_cycle === 'monthly' ? 'bulan' : $service->billing_cycle }}
              </p>
              <p class="fw-medium text-theme mt-1 mb-0" style="font-size:12px">
                + Rp {{ number_format($prorated, 0, ',', '.') }} sekarang (prorata sisa siklus)
              </p>

              <form method="POST" action="{{ route('client.services.upgrade.request', $service) }}" class="mt-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $option->id }}">
                <button type="submit" class="btn btn-theme w-100">Pilih Paket Ini</button>
              </form>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <p class="text-muted mt-4 mb-0" style="font-size:12px">
      Biaya di atas adalah selisih prorata untuk sisa hari sampai jatuh tempo berikutnya
      ({{ $service->next_due_date?->format('d M Y') }}) — bukan harga paket baru dari awal siklus.
      Mulai perpanjangan berikutnya, tagihan otomatis memakai harga paket baru.
    </p>
  @endif
@endsection
