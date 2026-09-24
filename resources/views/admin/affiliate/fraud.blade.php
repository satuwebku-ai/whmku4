@extends('layouts.admin')

@section('title', 'Fraud Review Affiliate')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Fraud Review Affiliate</h1>
      <p class="small text-muted mb-0">Tinjau referral dan konversi yang memiliki sinyal risiko sebelum komisi dicairkan.</p>
    </div>
    <a href="{{ route('admin.affiliate.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Program Affiliate</a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Affiliate</th>
            <th class="py-3">Client</th>
            <th class="py-3">Invoice</th>
            <th class="py-3">Alasan / Sinyal</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Review</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($flags as $flag)
            <tr>
              <td class="px-4 py-3">
                <a href="{{ route('admin.affiliate.show', $flag->affiliate) }}" style="font-family:monospace">{{ $flag->affiliate->code }}</a>
                <br><span class="small text-muted">{{ $flag->affiliate->client?->name }}</span>
              </td>
              <td class="py-3">{{ $flag->client?->name }}<br><span class="small text-muted">{{ $flag->client?->email }}</span></td>
              <td class="py-3">{{ $flag->invoice?->invoice_number ?? $flag->conversion?->invoice?->invoice_number ?? '—' }}</td>
              <td class="py-3 small">
                <div>{{ $flag->reason }}</div>
                <span class="text-muted">{{ collect($flag->signals ?? [])->keys()->implode(', ') }}</span>
              </td>
              <td class="py-3"><span class="badge badge-soft-warning">{{ ucfirst($flag->status) }}</span></td>
              <td class="text-end px-4 py-3">
                <form method="POST" action="{{ route('admin.affiliate.fraud.review', $flag) }}" class="d-inline-flex gap-1">
                  @csrf
                  <input type="hidden" name="status" value="approved">
                  <button type="submit" class="btn btn-success btn-sm">Approve</button>
                </form>
                <form method="POST" action="{{ route('admin.affiliate.fraud.review', $flag) }}" class="d-inline-flex gap-1">
                  @csrf
                  <input type="hidden" name="status" value="rejected">
                  <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                </form>
                <form method="POST" action="{{ route('admin.affiliate.fraud.review', $flag) }}" class="d-inline-flex gap-1">
                  @csrf
                  <input type="hidden" name="status" value="blocked">
                  <button type="submit" class="btn btn-outline-dark btn-sm">Block</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada item fraud yang menunggu review.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($flags->hasPages())
      <div class="px-4 py-3 border-top">{{ $flags->links('pagination.bootstrap') }}</div>
    @endif
  </div>
@endsection