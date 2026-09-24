@extends('layouts.admin')

@section('title', 'Detail Affiliate')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1" style="font-family:monospace">{{ $affiliate->code }}</h1>
      <p class="small text-muted mb-0">{{ $affiliate->client?->name }} — {{ $affiliate->client?->email }}</p>
    </div>
    <a href="{{ route('admin.affiliate.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Kembali</a>
  </div>

  @if ($affiliate->status === 'pending')
    <div class="card border rounded-4 p-3 mb-4 d-flex flex-row gap-2 align-items-center">
      <span class="small text-muted flex-grow-1">Affiliate ini masih menunggu persetujuan.</span>
      <form method="POST" action="{{ route('admin.affiliate.approve', $affiliate) }}">
        @csrf
        <button type="submit" class="btn btn-success btn-sm">Setujui</button>
      </form>
      <form method="POST" action="{{ route('admin.affiliate.reject', $affiliate) }}" onsubmit="return confirm('Tolak affiliate ini?')">
        @csrf
        <input type="hidden" name="reason" value="Ditolak oleh admin">
        <button type="submit" class="btn btn-outline-danger btn-sm">Tolak</button>
      </form>
    </div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Klik</div>
        <div class="h5 fw-bold mb-0">{{ $summary['clicks'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Referral</div>
        <div class="h5 fw-bold mb-0">{{ $summary['referrals'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Konversi</div>
        <div class="h5 fw-bold mb-0">{{ $summary['conversions'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Komisi Pending</div>
        <div class="h6 fw-bold mb-0">Rp {{ number_format($summary['commission_pending'], 0, ',', '.') }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Komisi Disetujui</div>
        <div class="h6 fw-bold mb-0">Rp {{ number_format($summary['commission_approved'], 0, ',', '.') }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Saldo Wallet</div>
        <div class="h6 fw-bold mb-0">Rp {{ number_format($summary['wallet_balance'], 0, ',', '.') }}</div>
      </div>
    </div>
  </div>

  <div class="card border rounded-4 mb-4">
    <div class="px-4 py-3 border-bottom fw-bold small text-uppercase text-muted">Konversi Terakhir</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Invoice</th>
            <th class="py-3">Jenis Produk</th>
            <th class="py-3">Nominal</th>
            <th class="py-3">Tanggal</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($conversions as $c)
            <tr>
              <td class="px-4 py-3">{{ $c->invoice?->invoice_number }}</td>
              <td class="py-3 text-muted">{{ $c->product_type ?? '—' }}</td>
              <td class="py-3">Rp {{ number_format($c->amount, 0, ',', '.') }}</td>
              <td class="py-3 small text-muted">{{ $c->created_at->format('d M Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada konversi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card border rounded-4">
    <div class="px-4 py-3 border-bottom fw-bold small text-uppercase text-muted">Komisi Terakhir</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">#</th>
            <th class="py-3">Nominal</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($commissions as $com)
            <tr>
              <td class="px-4 py-3">{{ $com->id }}</td>
              <td class="py-3">Rp {{ number_format($com->amount, 0, ',', '.') }}</td>
              <td class="py-3">
                @php $cBadge = ['pending' => 'badge-soft-warning', 'approved' => 'badge-soft-success', 'cancelled' => 'badge-soft-danger']; @endphp
                <span class="badge {{ $cBadge[$com->status] }}">{{ ucfirst($com->status) }}</span>
              </td>
              <td class="text-end px-4 py-3">
                @if ($com->status === 'pending')
                  <form method="POST" action="{{ route('admin.affiliate.commissions.approve', $com) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada komisi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

@endsection
