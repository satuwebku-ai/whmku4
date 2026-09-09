@extends('client.layout')
@section('title', $invoice->invoice_number)

@section('content')
  @php
    $badgeMap = ['unpaid' => 'badge-soft-warning', 'paid' => 'badge-soft-success', 'overdue' => 'badge-soft-danger', 'cancelled' => 'badge-soft-secondary'];
  @endphp

  <a href="{{ route('client.invoices') }}" class="text-decoration-none text-muted" style="font-size:12px">&larr; Kembali ke Invoice</a>

  <div class="d-flex align-items-center justify-content-between mt-2 mb-4 flex-wrap gap-3">
    <h1 class="h4 fw-bold text-dark mb-0">{{ $invoice->invoice_number }}</h1>
    <div class="d-flex align-items-center gap-2">
      <span class="badge {{ $badgeMap[$invoice->is_overdue ? 'overdue' : $invoice->status] ?? 'badge-soft-secondary' }}">
        {{ $invoice->is_overdue ? 'Terlambat' : ucfirst($invoice->status) }}
      </span>
      <a href="{{ route('client.invoices.pdf', $invoice) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-file-arrow-down" style="font-size:11px"></i> Unduh PDF
      </a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card-public p-4">
        <div class="d-flex justify-content-between align-items-start mb-4 pb-4 border-bottom">
          <div>
            <p class="text-muted mb-0" style="font-size:11px">Ditagihkan kepada</p>
            <p class="fw-semibold text-dark mt-1 mb-0">{{ $invoice->client->name }}</p>
            <p class="text-muted mb-0" style="font-size:14px">{{ $invoice->client->email }}</p>
          </div>
          <div class="text-end">
            <p class="text-muted mb-0" style="font-size:11px">Tanggal Terbit</p>
            <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $invoice->issue_date->format('d M Y') }}</p>
            <p class="text-muted mt-2 mb-0" style="font-size:11px">Jatuh Tempo</p>
            <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $invoice->due_date->format('d M Y') }}</p>
          </div>
        </div>

        <div class="table-responsive mb-4">
          <table class="table mb-0">
            <thead>
              <tr class="small text-uppercase text-muted border-bottom">
                <th class="pb-2">Deskripsi</th>
                <th class="pb-2 text-end">Jumlah</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($invoice->items as $lineItem)
                <tr>
                  <td class="py-3 text-dark" style="font-size:14px">{{ $lineItem->description }}</td>
                  <td class="py-3 text-end text-dark" style="font-size:14px">Rp {{ number_format($lineItem->amount, 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr>
                  <td class="py-3 text-dark" style="font-size:14px">
                    {{ $invoice->order->product_name ?? 'Tagihan layanan' }}
                    @if ($invoice->order)
                      <span class="d-block text-muted" style="font-size:11px">Order #{{ $invoice->order->order_number }}</span>
                    @endif
                  </td>
                  <td class="py-3 text-end text-dark" style="font-size:14px">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-column gap-2 pt-3 border-top" style="font-size:14px">
          <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><span class="text-dark">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Pajak</span><span class="text-dark">Rp {{ number_format($invoice->tax, 0, ',', '.') }}</span></div>
          @if ($invoice->discount > 0)
            <div class="d-flex justify-content-between text-success">
              <span>Kupon{{ $invoice->coupon ? ' ' . $invoice->coupon->code : '' }}</span>
              <span>- Rp {{ number_format($invoice->discount, 0, ',', '.') }}</span>
            </div>
          @endif
          <div class="d-flex justify-content-between fw-bold text-dark pt-2 border-top" style="font-size:1.1rem">
            <span>Total</span><span>Rp {{ number_format($invoice->total, 0, ',', '.') }}</span>
          </div>
        </div>

        @if ($invoice->notes)
          <div class="mt-4 pt-4 border-top">
            <p class="text-muted mb-1" style="font-size:11px">Catatan</p>
            <p class="text-muted mb-0" style="font-size:14px;white-space:pre-line">{{ $invoice->notes }}</p>
          </div>
        @endif
      </div>
    </div>

    {{-- Panel pembayaran --}}
    <div class="col-12 col-lg-4 d-flex flex-column gap-4">
      @if ($invoice->status === 'paid')
        <div class="card-public p-4 text-center" style="border-color:#a7f3d0!important;background:#f0fdf4">
          <i class="fa-solid fa-circle-check text-success mb-2" style="font-size:1.75rem"></i>
          <p class="fw-semibold mb-1" style="color:#065f46">Invoice Lunas</p>
          <p class="mb-0" style="font-size:11px;color:#047857">
            Dibayar {{ $invoice->paid_at?->format('d M Y') }}
            @if ($invoice->payment_method) via {{ $invoice->payment_method }} @endif
          </p>
        </div>

      @elseif ($invoice->status === 'cancelled')
        <div class="card-public p-4 text-center text-muted">
          <p class="mb-0" style="font-size:14px">Invoice ini sudah dibatalkan.</p>
        </div>

      @else
        {{-- Ada pembayaran yang belum selesai --}}
        @if ($pendingPayment && $pendingPayment->payment_url)
          <div class="card-public p-4" style="border-color:#fde68a!important;background:#fffbeb">
            <p class="fw-semibold mb-1" style="font-size:14px;color:#92400e">Pembayaran Sedang Diproses</p>
            <p class="mb-3" style="font-size:11px;color:#b45309">
              Anda sudah memulai pembayaran ({{ $pendingPayment->reference }}). Lanjutkan di link berikut,
              atau pilih metode lain di bawah.
            </p>
            <a href="{{ $pendingPayment->payment_url }}" class="btn btn-theme w-100">
              <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:11px"></i> Lanjutkan Pembayaran
            </a>
          </div>
        @endif

        @php $clientBalance = (float) (auth('client')->user()->balance ?? 0); @endphp
        @if (! $invoice->is_topup && $clientBalance >= (float) $invoice->total)
          <div class="card-public p-4" style="border-color:#a7f3d0!important;background:#f0fdf4">
            <p class="mb-2" style="font-size:14px;color:#065f46">
              <i class="fa-solid fa-wallet"></i>
              Saldo Anda cukup — <b>Rp {{ number_format($clientBalance, 0, ',', '.') }}</b>
            </p>
            <form method="POST" action="{{ route('client.balance.pay', $invoice) }}">
              @csrf
              <button type="submit" class="btn btn-success w-100 btn-sm">
                Bayar dengan Saldo
              </button>
            </form>
          </div>
        @endif

        <div class="card-public p-4">
          <h2 class="small fw-bold text-dark mb-1">Bayar Invoice</h2>
          <p class="text-muted mb-3" style="font-size:12px">Pilih metode pembayaran yang Anda inginkan.</p>

          @if ($gateways->isEmpty())
            <p class="text-muted mb-0" style="font-size:14px">Belum ada metode pembayaran tersedia. Silakan hubungi support.</p>
          @else
            {{-- Jalan pintas QRIS tertanam --}}
            @php $qrisGateway = $gateways->first(fn ($g) => $g->supportsEmbeddedQris()); @endphp
            @if ($qrisGateway)
              <a href="{{ route('client.invoices.qris', [$invoice, $qrisGateway]) }}"
                 class="d-flex align-items-center gap-3 p-3 mb-3 rounded-3 text-decoration-none" style="border:2px solid rgba(79,70,229,.25);background:rgba(79,70,229,.04)">
                <span class="rounded-3 bg-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;border:1px solid rgba(79,70,229,.2)">
                  <i class="fa-solid fa-qrcode text-theme"></i>
                </span>
                <span class="flex-grow-1">
                  <span class="d-block fw-semibold text-dark" style="font-size:14px">Bayar dengan QRIS</span>
                  <span class="d-block text-muted" style="font-size:11px">Scan langsung dari halaman ini — tanpa pindah situs</span>
                </span>
                <i class="fa-solid fa-arrow-right text-theme" style="font-size:11px"></i>
              </a>

              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="flex-grow-1 border-top"></span>
                <span class="text-muted" style="font-size:11px">atau metode lain</span>
                <span class="flex-grow-1 border-top"></span>
              </div>
            @endif

            <form method="POST" action="{{ route('client.invoices.pay', $invoice) }}" class="d-flex flex-column gap-3">
              @csrf

              <div class="d-flex flex-column gap-2">
                @foreach ($gateways as $gw)
                  @php $fee = $gw->calculateFee((float) $invoice->total); @endphp
                  <label class="d-flex align-items-start gap-3 p-3 rounded-3 border" style="cursor:pointer">
                    <input type="radio" name="payment_gateway_id" value="{{ $gw->id }}" required style="margin-top:2px">
                    <span class="flex-grow-1 min-w-0">
                      <span class="d-block fw-medium text-dark" style="font-size:14px">{{ $gw->name }}</span>
                      @if ($fee > 0)
                        <span class="d-block text-muted" style="font-size:11px">
                          + biaya Rp {{ number_format($fee, 0, ',', '.') }}
                          — total Rp {{ number_format($invoice->total + $fee, 0, ',', '.') }}
                        </span>
                      @else
                        <span class="d-block text-success" style="font-size:11px">Tanpa biaya tambahan</span>
                      @endif
                    </span>
                  </label>
                @endforeach
              </div>

              <button type="submit" class="btn btn-theme w-100">
                <i class="fa-solid fa-credit-card" style="font-size:11px"></i> Lanjutkan Pembayaran
              </button>
            </form>
          @endif
        </div>

        {{-- Instruksi transfer manual --}}
        @foreach ($gateways->where('driver', 'manual') as $manual)
          @if ($manual->instructions)
            <div class="card-public p-4">
              <h2 class="small fw-bold text-dark mb-2">{{ $manual->name }}</h2>
              <div class="text-muted rounded-3 p-3" style="font-size:14px;white-space:pre-line;background:#f8fafc">{{ $manual->instructions }}</div>

              @if ($pendingPayment && $pendingPayment->payment_gateway_id === $manual->id)
                <div class="mt-3 pt-3 border-top">
                  @if ($pendingPayment->proof_path)
                    <p class="mb-0 rounded-3 px-3 py-2" style="font-size:12px;color:#047857;background:#f0fdf4;border:1px solid #a7f3d0">
                      <i class="fa-solid fa-circle-check"></i> Bukti transfer sudah dikirim, menunggu diperiksa tim kami.
                    </p>
                  @else
                    <p class="text-muted mb-2" style="font-size:12px">
                      Sudah transfer? Unggah buktinya di sini supaya kami tahu untuk memeriksanya —
                      tidak perlu menghubungi kami secara terpisah.
                    </p>
                    <form method="POST" action="{{ route('client.payment.confirm', $pendingPayment) }}"
                          enctype="multipart/form-data" class="d-flex flex-column gap-2">
                      @csrf
                      <input type="file" name="proof" accept="image/*,application/pdf" required class="form-control form-control-sm">
                      @error('proof') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
                      <textarea name="note" rows="2" placeholder="Catatan tambahan (opsional)" class="form-control form-control-sm"></textarea>
                      <button type="submit" class="btn btn-theme w-100 btn-sm">
                        <i class="fa-solid fa-upload" style="font-size:11px"></i> Kirim Bukti Transfer
                      </button>
                    </form>
                  @endif
                </div>
              @else
                <p class="text-muted mt-2 mb-0" style="font-size:11px">
                  Cantumkan nomor invoice <b>{{ $invoice->invoice_number }}</b> saat konfirmasi transfer.
                </p>
              @endif
            </div>
          @endif
        @endforeach
      @endif
    </div>
  </div>
@endsection
