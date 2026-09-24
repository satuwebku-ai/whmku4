@extends('layouts.admin')

@section('title', 'Payout Affiliate')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Payout Affiliate</h1>
      <p class="small text-muted mb-0">Cairkan permintaan payout ke rekening affiliate.</p>
    </div>
    <a href="{{ route('admin.affiliate.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Program Affiliate</a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <select name="status" class="form-select form-select-sm" style="max-width:12rem" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        @foreach (['pending' => 'Pending', 'approved' => 'Disetujui', 'processing' => 'Processing', 'paid' => 'Sudah Dibayar', 'failed' => 'Gagal', 'rejected' => 'Ditolak'] as $val => $label)
          <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
        @endforeach
      </select>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">#</th>
            <th class="py-3">Affiliate</th>
            <th class="py-3">Nominal</th>
            <th class="py-3">Rekening</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($payouts as $p)
            <tr>
              <td class="px-4 py-3">{{ $p->id }}</td>
              <td class="py-3">
                <a href="{{ route('admin.affiliate.show', $p->affiliate) }}" style="font-family:monospace">{{ $p->affiliate->code }}</a>
                <br><span class="small text-muted">{{ $p->affiliate->client?->name }}</span>
              </td>
              <td class="py-3">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
              <td class="py-3 small text-muted">{{ $p->bank_name }} — {{ $p->bank_account_number }}<br>a.n. {{ $p->bank_account_name }}</td>
              <td class="py-3">
                @php $pBadge = ['pending' => 'badge-soft-warning', 'approved' => 'badge-soft-info', 'processing' => 'badge-soft-info', 'paid' => 'badge-soft-success', 'failed' => 'badge-soft-danger', 'rejected' => 'badge-soft-danger']; @endphp
                <span class="badge {{ $pBadge[$p->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($p->status) }}</span>
              </td>
              <td class="text-end px-4 py-3">
                @if ($p->status === 'pending')
                  <form method="POST" action="{{ route('admin.affiliate.payouts.approve', $p) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                  </form>
                  <form method="POST" action="{{ route('admin.affiliate.payouts.reject', $p) }}" class="d-inline" onsubmit="return confirm('Tolak payout ini?')">
                    @csrf
                    <input type="hidden" name="reason" value="Ditolak oleh admin">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Tolak</button>
                  </form>
                @elseif ($p->status === 'approved')
                  <form method="POST" action="{{ route('admin.affiliate.payouts.process', $p) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">Process</button>
                  </form>
                  <form method="POST" action="{{ route('admin.affiliate.payouts.paid', $p) }}" class="d-inline">
                    @csrf
                    <input type="text" name="transaction_reference" class="form-control form-control-sm d-inline-block" style="width:10rem" placeholder="Ref transfer">
                    <button type="submit" class="btn btn-outline-success btn-sm">Tandai Dibayar</button>
                  </form>
                @elseif ($p->status === 'processing')
                  <form method="POST" action="{{ route('admin.affiliate.payouts.paid', $p) }}" class="d-inline">
                    @csrf
                    <input type="text" name="transaction_reference" class="form-control form-control-sm d-inline-block" style="width:10rem" placeholder="Ref transfer">
                    <button type="submit" class="btn btn-outline-success btn-sm">Paid</button>
                  </form>
                  <form method="POST" action="{{ route('admin.affiliate.payouts.failed', $p) }}" class="d-inline" onsubmit="return confirm('Tandai payout gagal dan kembalikan saldo?')">
                    @csrf
                    <input type="hidden" name="reason" value="Transfer gagal">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Gagal</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada permintaan payout.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($payouts->hasPages())
      <div class="px-4 py-3 border-top">{{ $payouts->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
