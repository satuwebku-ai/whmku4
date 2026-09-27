@extends('client.layout')
@section('title', 'Pilih Metode Pembayaran')

@section('content')
  <a href="{{ route('client.invoices.show', $invoice) }}" class="text-decoration-none text-muted" style="font-size:12px">
    &larr; Kembali ke Invoice {{ $invoice->invoice_number }}
  </a>

  <div class="mt-2 mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Pilih Metode Pembayaran</h1>
    <p class="text-muted mb-0">
      Total tagihan: <b class="text-dark">Rp {{ number_format($total, 0, ',', '.') }}</b>
    </p>
  </div>

  @if ($grouped->isEmpty())
    <div class="card-public p-5 text-center">
      <p class="text-muted mb-0" style="font-size:14px">Tidak ada metode pembayaran yang tersedia saat ini. Silakan hubungi support kami.</p>
    </div>
  @endif

  @foreach ($grouped as $category => $methods)
    <div class="mb-4">
      <h2 class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">{{ $category }}</h2>
      <div class="row g-3">
        @foreach ($methods as $method)
          <div class="col-sm-6 col-lg-4">
            <form method="POST" action="{{ route('client.invoices.pay-duitku', $invoice) }}">
              @csrf
              <input type="hidden" name="payment_gateway_id" value="{{ $gateway->id }}">
              <input type="hidden" name="method_code" value="{{ $method['paymentMethod'] }}">
              <button type="submit" class="card-public p-3 w-100 text-start d-flex align-items-center gap-3 border-0">
                @if (! empty($method['paymentImage']))
                  <img src="{{ $method['paymentImage'] }}" alt="{{ $method['paymentName'] }}" style="height:32px;width:auto;object-fit:contain" class="flex-shrink-0" loading="lazy">
                @endif
                <div class="min-w-0">
                  <p class="fw-medium text-dark text-truncate mb-0" style="font-size:14px">{{ $method['paymentName'] }}</p>
                  <p class="text-muted mb-0" style="font-size:11px">
                    {{ (float) ($method['totalFee'] ?? 0) > 0 ? 'Biaya Rp ' . number_format((float) $method['totalFee'], 0, ',', '.') : 'Tanpa biaya tambahan' }}
                  </p>
                </div>
              </button>
            </form>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach
@endsection
