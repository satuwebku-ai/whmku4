@extends('layouts.admin')

@section('title', 'Komisi Affiliate')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Komisi Affiliate</h1>
      <p class="small text-muted mb-0">Setujui komisi supaya masuk ke wallet affiliate.</p>
    </div>
    <a href="{{ route('admin.affiliate.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Program Affiliate</a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <select name="status" class="form-select form-select-sm" style="max-width:12rem" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        @foreach (['pending' => 'Pending', 'approved' => 'Disetujui', 'cancelled' => 'Dibatalkan'] as $val => $label)
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
            <th class="py-3">Invoice</th>
            <th class="py-3">Nominal</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($commissions as $com)
            <tr>
              <td class="px-4 py-3">{{ $com->id }}</td>
              <td class="py-3">
                <a href="{{ route('admin.affiliate.show', $com->affiliate) }}" style="font-family:monospace">{{ $com->affiliate->code }}</a>
                <br><span class="small text-muted">{{ $com->affiliate->client?->name }}</span>
              </td>
              <td class="py-3">{{ $com->conversion?->invoice?->invoice_number }}</td>
              <td class="py-3">Rp {{ number_format($com->amount, 0, ',', '.') }}</td>
              <td class="py-3">
                @php $cBadge = ['pending' => 'badge-soft-warning', 'approved' => 'badge-soft-success', 'cancelled' => 'badge-soft-danger']; @endphp
                <span class="badge {{ $cBadge[$com->status] ?? 'badge-soft-danger' }}">{{ ucfirst($com->status) }}</span>
              </td>
              <td class="text-end px-4 py-3">
                @if ($com->status === 'pending')
                  <form method="POST" action="{{ route('admin.affiliate.commissions.approve', $com) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                  </form>
                  <form method="POST" action="{{ route('admin.affiliate.commissions.cancel', $com) }}" class="d-inline" onsubmit="return confirm('Batalkan komisi ini?')">
                    @csrf
                    <input type="hidden" name="reason" value="Dibatalkan oleh admin">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Batalkan</button>
                  </form>
                @endif
                @elseif ($com->status === 'approved')
                  <form method="POST" action="{{ route('admin.affiliate.commissions.reverse', $com) }}" class="d-inline" onsubmit="return confirm('Reversal akan mengurangi wallet affiliate. Lanjutkan?')">
                    @csrf
                    <input type="hidden" name="reason" value="Refund atau chargeback">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Reversal</button>
                  </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada komisi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($commissions->hasPages())
      <div class="px-4 py-3 border-top">{{ $commissions->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
