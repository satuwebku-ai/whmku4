@extends('client.layout')
@section('title', 'Saldo Saya')

@section('content')
  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Saldo Saya</h1>
    <p class="text-muted mb-0">Isi ulang saldo untuk membayar invoice lebih cepat, tanpa pilih metode pembayaran berulang kali.</p>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="dash-card overflow-hidden">
        <div class="px-4 py-3 border-bottom d-flex align-items-center gap-2">
          <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:30px;height:30px;background:rgba(79,70,229,.1);color:var(--lumora-theme)">
            <i class="fa-solid fa-clock-rotate-left" style="font-size:12px"></i>
          </span>
          <h2 class="small fw-bold text-dark mb-0">Riwayat Saldo</h2>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="px-4 py-3">Tanggal</th>
                <th class="py-3">Keterangan</th>
                <th class="text-end py-3">Jumlah</th>
                <th class="text-end px-4 py-3">Saldo Setelahnya</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($logs as $log)
                <tr>
                  <td class="px-4 py-3 text-muted" style="font-size:11px">{{ $log->created_at->format('d M Y H:i') }}</td>
                  <td class="py-3 text-dark" style="font-size:14px">
                    <span class="badge {{ $log->amount >= 0 ? 'badge-soft-success' : 'badge-soft-secondary' }} me-1" style="font-size:10px">{{ $log->type_label }}</span>
                    {{ $log->description }}
                  </td>
                  <td class="text-end py-3 fw-medium {{ $log->amount >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:14px">
                    {{ $log->amount >= 0 ? '+' : '' }}Rp {{ number_format($log->amount, 0, ',', '.') }}
                  </td>
                  <td class="text-end px-4 py-3 text-muted" style="font-size:14px">Rp {{ number_format($log->balance_after, 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-5">Belum ada riwayat saldo.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($logs->hasPages())
          <div class="px-4 py-3 border-top">{{ $logs->links('pagination.bootstrap') }}</div>
        @endif
      </div>
    </div>

    <div class="col-12 col-lg-4 d-flex flex-column gap-4">
      <div class="card-public p-4 text-white" style="background:linear-gradient(135deg,var(--lumora-theme),#4338ca);box-shadow:0 8px 20px -8px rgba(79,70,229,.5)">
        <p class="mb-1" style="font-size:11px;color:rgba(255,255,255,.7)">Saldo Tersedia</p>
        <p class="fw-bold mb-0" style="font-size:1.75rem">Rp {{ number_format((float) $client->balance, 0, ',', '.') }}</p>
      </div>

      <div class="dash-card p-4">
        <h2 class="small fw-bold text-dark mb-3">Isi Ulang Saldo</h2>
        <form method="POST" action="{{ route('client.balance.topup') }}" class="d-flex flex-column gap-3">
          @csrf
          <div class="row g-2">
            @foreach ([50000, 100000, 250000, 500000, 1000000, 2000000] as $preset)
              <div class="col-4">
                <button type="button" onclick="document.getElementById('topupAmount').value = {{ $preset }}"
                        class="btn btn-outline-secondary btn-sm w-100">
                  {{ number_format($preset / 1000, 0) }}rb
                </button>
              </div>
            @endforeach
          </div>
          <div>
            <label class="form-label">Nominal (Rp)</label>
            <input type="number" name="amount" id="topupAmount" min="10000" step="1000" required
                   placeholder="100000" class="form-control">
            @error('amount') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <button type="submit" class="btn btn-theme w-100">
            <i class="fa-solid fa-wallet" style="font-size:11px"></i> Isi Ulang Sekarang
          </button>
        </form>
      </div>
    </div>
  </div>
@endsection
