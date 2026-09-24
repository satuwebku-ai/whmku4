@extends('layouts.admin')

@section('title', 'Program Affiliate')

@section('content')

  @php use App\Models\Setting; @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Program Affiliate</h1>
      <p class="small text-muted mb-0">Daftar affiliate, klik, konversi, dan komisi.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.affiliate.commissions.index') }}" class="btn btn-outline-secondary btn-sm">Komisi</a>
      <a href="{{ route('admin.affiliate.payouts.index') }}" class="btn btn-outline-secondary btn-sm">Payout</a>
      <a href="{{ route('admin.affiliate.rules.index') }}" class="btn btn-outline-secondary btn-sm">Rules</a>
      <a href="{{ route('admin.affiliate.fraud.index') }}" class="btn btn-outline-secondary btn-sm">Fraud Review</a>
      <a href="{{ route('admin.settings.affiliate') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-gear" style="font-size:11px"></i> Atur Komisi</a>
    </div>
  </div>

  <div class="rounded-3 p-3 mb-4" style="background:#eef2ff;color:#4338ca;font-size:13px;max-width:48rem">
    <i class="fa-solid fa-circle-info"></i>
    Komisi default saat ini: affiliate dapat
    <b>{{ Setting::get('affiliate_commission_type', 'percentage') === 'percentage' ? Setting::get('affiliate_commission_value', 10) . '%' : 'Rp ' . number_format((float) Setting::get('affiliate_commission_value', 10), 0, ',', '.') }}</b>
    dari {{ Setting::get('affiliate_commission_type', 'percentage') === 'percentage' ? 'nilai' : 'setiap' }} transaksi klien referral,
    {{ Setting::get('affiliate_commission_repeat', true) ? 'berulang setiap perpanjangan' : 'satu kali di pembelian pertama' }}.
    Affiliate baru berstatus <b>pending</b> sampai disetujui di sini. <a href="{{ route('admin.settings.affiliate') }}" style="color:inherit;text-decoration:underline">Ubah pengaturan ini</a>.
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Menunggu Approval</div>
        <div class="h5 fw-bold mb-0">{{ $summary['affiliates_pending'] }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border rounded-4 p-3">
          <div class="small text-muted">Fraud Review</div>
          <div class="h5 fw-bold mb-0">{{ $summary['fraud_review'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Affiliate Aktif</div>
        <div class="h5 fw-bold mb-0">{{ $summary['affiliates_approved'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Total Komisi Disetujui</div>
        <div class="h5 fw-bold mb-0">Rp {{ number_format($summary['commission_approved_total'], 0, ',', '.') }}</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border rounded-4 p-3">
        <div class="small text-muted">Payout Menunggu</div>
        <div class="h5 fw-bold mb-0">{{ $summary['payouts_pending'] }} <span class="small text-muted">(Rp {{ number_format($summary['payouts_pending_amount'], 0, ',', '.') }})</span></div>
      </div>
    </div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Kode</th>
            <th class="py-3">Client</th>
            <th class="py-3">Status</th>
            <th class="py-3">Rekening</th>
            <th class="py-3">Daftar</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($affiliates as $affiliate)
            <tr>
              <td class="px-4 py-3 fw-bold text-dark" style="font-family:monospace">
                <a href="{{ route('admin.affiliate.show', $affiliate) }}">{{ $affiliate->code }}</a>
              </td>
              <td class="py-3">{{ $affiliate->client?->name }}<br><span class="small text-muted">{{ $affiliate->client?->email }}</span></td>
              <td class="py-3">
                @php $statusBadge = ['pending' => 'badge-soft-warning', 'approved' => 'badge-soft-success', 'rejected' => 'badge-soft-danger', 'suspended' => 'badge-soft-secondary']; @endphp
                <span class="badge {{ $statusBadge[$affiliate->status] }}">{{ ucfirst($affiliate->status) }}</span>
              </td>
              <td class="py-3 small text-muted">{{ $affiliate->bank_account_number ?? '—' }}</td>
              <td class="py-3 small text-muted">{{ $affiliate->created_at->format('d M Y') }}</td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  @if ($affiliate->status === 'pending')
                    <form method="POST" action="{{ route('admin.affiliate.approve', $affiliate) }}">
                      @csrf
                      <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                    </form>
                  @endif
                  <a href="{{ route('admin.affiliate.show', $affiliate) }}" class="btn btn-outline-secondary btn-sm">Detail</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada affiliate yang mendaftar.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($affiliates->hasPages())
      <div class="px-4 py-3 border-top">{{ $affiliates->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
