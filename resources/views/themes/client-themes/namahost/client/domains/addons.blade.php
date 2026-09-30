@extends('client.layout')
@section('title', 'Addons — ' . $domain->domain_name)

@section('content')
  <a href="{{ route('client.domains.show', $domain) }}" class="text-decoration-none text-muted" style="font-size:12px">
    &larr; Kembali ke {{ $domain->domain_name }}
  </a>

  <div class="mt-2 mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Addons — {{ $domain->domain_name }}</h1>
    <p class="text-muted mb-0">Fitur tambahan yang bisa dipasang di domain ini.</p>
  </div>

  <div class="card-public p-4">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div class="d-flex align-items-start gap-3">
        <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:rgba(79,70,229,.1);color:#4f46e5">
          <i class="fa-solid fa-user-shield" style="font-size:14px"></i>
        </span>
        <div>
          <h2 class="fw-semibold text-dark mb-1" style="font-size:14px">ID Protection (WHOIS Privacy)</h2>
          <p class="text-muted mb-0" style="font-size:12px;max-width:28rem">Sembunyikan data pribadi (nama, alamat, email, telepon) dari pencarian WHOIS publik — mencegah spam dan penyalahgunaan data.</p>

          @php
            $privacyActive = $domain->hasActivePrivacy();
            $privacyDaysLeft = $domain->privacyDaysLeft();
            $privacyPrice = (float) \App\Models\Setting::get('whois_privacy_price', 0);
          @endphp

          <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
            <span class="badge {{ $privacyActive ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $privacyActive ? 'Aktif' : 'Nonaktif' }}</span>

            @if ($privacyActive)
              <span style="font-size:12px;{{ $privacyDaysLeft !== null && $privacyDaysLeft <= 30 ? 'color:#b45309' : 'color:#94a3b8' }}">
                s.d. {{ $domain->privacy_expires_at->format('d M Y') }}
                @if ($privacyDaysLeft !== null && $privacyDaysLeft <= 30)
                  ({{ $privacyDaysLeft }} hari lagi)
                @endif
              </span>
            @elseif ($domain->privacy_expires_at && $domain->privacy_expires_at->isPast())
              <span class="text-danger" style="font-size:12px">Kedaluwarsa {{ $domain->privacy_expires_at->format('d M Y') }}</span>
            @endif
          </div>

          @if (! is_null($privacyAtRegistrar ?? null) && $privacyAtRegistrar !== $privacyActive)
            <p class="text-danger mt-2 mb-0" style="font-size:12px">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Status di registrar: <b>{{ $privacyAtRegistrar ? 'Aktif' : 'Nonaktif' }}</b> — tidak cocok dengan catatan di sini. Hubungi support untuk disesuaikan.
            </p>
          @endif
        </div>
      </div>

      <div class="flex-shrink-0">
        @if ($domain->privacy_invoice_id)
          <a href="{{ route('client.invoices.show', $domain->privacy_invoice_id) }}" class="btn btn-theme btn-sm">
            Bayar Sekarang
          </a>
        @else
          <form method="POST" action="{{ route('client.domains.privacy', $domain) }}">
            @csrf
            <button type="submit" class="btn btn-sm {{ $privacyActive ? 'btn-outline-secondary' : 'btn-theme' }}">
              @if ($privacyActive)
                Matikan
              @else
                {{ $domain->privacy_expires_at ? 'Perpanjang' : 'Aktifkan' }}{{ $privacyPrice > 0 ? ' — Rp ' . number_format($privacyPrice, 0, ',', '.') . '/thn' : '' }}
              @endif
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
@endsection
