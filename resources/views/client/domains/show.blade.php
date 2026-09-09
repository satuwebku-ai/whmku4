@extends('client.layout')
@section('title', $domain->domain_name)

@section('content')
  @php
    $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'expired' => 'badge-soft-danger'];
  @endphp

  <a href="{{ route('client.domains') }}" class="text-decoration-none text-muted" style="font-size:12px">&larr; Kembali ke Domain</a>

  <div class="d-flex align-items-center justify-content-between mt-2 mb-4 flex-wrap gap-3">
    <h1 class="h4 fw-bold text-dark mb-0">{{ $domain->domain_name }}</h1>
    <div class="d-flex align-items-center gap-2">
      @if ($domain->status === 'active' && ! $domain->renewal_invoice_id)
        <form method="POST" action="{{ route('client.domains.renew-now', $domain) }}"
              data-confirm="Buat invoice perpanjangan sekarang untuk {{ $domain->domain_name }}? Masa aktif akan bertambah dari tanggal kedaluwarsa saat ini ({{ $domain->expiry_date->format('d M Y') }}) setelah invoice ini dibayar — bukan dari hari ini."
              data-confirm-title="Perpanjang Sekarang" data-confirm-style="info" data-confirm-label="Ya, Buat Invoice">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-rotate" style="font-size:11px"></i> Perpanjang Sekarang
          </button>
        </form>
      @endif
      @if ($domain->provision_status === 'registered')
        <a href="{{ route('client.domains.addons', $domain) }}" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-puzzle-piece" style="font-size:11px"></i> Addons
        </a>
      @endif
      <span class="badge {{ $badgeMap[$domain->status === 'expired' ? 'expired' : $domain->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($domain->status) }}</span>
    </div>
  </div>

  @if ($domain->provision_status === 'needs_documents')
    <div class="card-public p-4 mb-4" style="border-color:#fde68a!important;background:#fffbeb">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <p class="mb-0" style="font-size:14px;color:#92400e">
          <i class="fa-solid fa-file-circle-exclamation"></i>
          Domain ini butuh dokumen tambahan sebelum bisa diaktifkan (persyaratan PANDI untuk TLD Indonesia).
        </p>
        <a href="{{ route('client.domains.documents', $domain) }}" class="btn btn-theme btn-sm flex-shrink-0">
          Unggah Dokumen
        </a>
      </div>
    </div>
  @endif

  {{-- Cuma tampil kalau invoicenya BELUM lunas. Sebelumnya notif ini
       tampil terus selamanya begitu renewal_invoice_id terisi, bahkan
       setelah klien sudah bayar -- karena kolom itu memang tidak pernah
       dikosongkan lagi setelah lunas (dan memang tidak seharusnya,
       supaya riwayat invoice perpanjangan tetap tertaut). --}}
  @if ($domain->renewal_invoice_id && $domain->renewalInvoice && $domain->renewalInvoice->status !== 'paid')
    <div class="card-public p-4 mb-4" style="border-color:#c7d2fe!important;background:rgba(79,70,229,.04)">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <p class="text-dark mb-0" style="font-size:14px">
          <i class="fa-solid fa-file-invoice text-theme"></i>
          Invoice perpanjangan <b>{{ $domain->renewalInvoice->invoice_number }}</b> sudah dibuat,
          jatuh tempo {{ $domain->renewalInvoice->due_date->format('d M Y') }}.
        </p>
        <a href="{{ route('client.invoices.show', $domain->renewalInvoice) }}" class="btn btn-theme btn-sm flex-shrink-0">
          Bayar Sekarang
        </a>
      </div>
    </div>
  @elseif ($domain->is_expiring_soon)
    <div class="card-public p-4 mb-4" style="border-color:#fde68a!important;background:#fffbeb">
      <p class="mb-0" style="font-size:14px;color:#92400e">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Domain ini akan kedaluwarsa {{ $domain->expiry_date->format('d M Y') }}.
        @if ($domain->auto_renew)
          Invoice perpanjangan akan dibuat otomatis mendekati tanggal tersebut.
        @else
          Perpanjangan Otomatis sedang nonaktif untuk domain ini — aktifkan di bawah atau hubungi kami sebelum tanggal tersebut.
        @endif
      </p>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Detail Domain</h2>
        <div class="row g-3">
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Tanggal Registrasi</p><p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $domain->register_date?->format('d M Y') ?? '—' }}</p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Kedaluwarsa</p><p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $domain->expiry_date?->format('d M Y') ?? '—' }}</p></div>

          <div class="col-sm-6">
            <p class="text-muted mb-0" style="font-size:11px">Perpanjangan Otomatis</p>
            <div class="d-flex align-items-center gap-2 mt-1">
              <span class="fw-medium text-dark" style="font-size:14px">{{ $domain->auto_renew ? 'Aktif' : 'Nonaktif' }}</span>
              @if ($domain->provision_status === 'registered')
                <form method="POST" action="{{ route('client.domains.auto-renew', $domain) }}">
                  @csrf
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" @checked($domain->auto_renew) onchange="this.form.submit()">
                  </div>
                </form>
              @endif
            </div>
          </div>

          <div class="col-sm-6">
            <p class="text-muted mb-0" style="font-size:11px">ID Protection (WHOIS Privacy)</p>
            @php $privacyActive = $domain->hasActivePrivacy(); @endphp
            <div class="d-flex align-items-center gap-2 mt-1">
              <span class="badge {{ $privacyActive ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $privacyActive ? 'Aktif' : 'Nonaktif' }}</span>
              @if ($domain->provision_status === 'registered')
                <a href="{{ route('client.domains.addons', $domain) }}" class="text-decoration-none text-theme" style="font-size:12px">Kelola di Addons &rarr;</a>
              @endif
            </div>
          </div>

          @if (! is_null($lockStatus))
            <div class="col-sm-6">
              <p class="text-muted mb-0" style="font-size:11px">Registrar Lock</p>
              <div class="d-flex align-items-center gap-2 mt-1">
                <span class="fw-medium text-dark" style="font-size:14px">{{ $lockStatus ? 'Terkunci' : 'Tidak Terkunci' }}</span>
                <form method="POST" action="{{ route('client.domains.lock', $domain) }}">
                  @csrf
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" @checked($lockStatus) onchange="this.form.submit()">
                  </div>
                </form>
              </div>
            </div>
          @endif

          @if (! is_null($theftStatus))
            <div class="col-sm-6">
              <p class="text-muted mb-0" style="font-size:11px">
                Theft Protection
                <i class="fa-solid fa-circle-question text-muted" title="Proteksi tambahan dari pencurian domain lewat perubahan data tanpa verifikasi ekstra — beda dari ID Protection"></i>
              </p>
              <div class="d-flex align-items-center gap-2 mt-1">
                <span class="fw-medium text-dark" style="font-size:14px">{{ $theftStatus ? 'Aktif' : 'Nonaktif' }}</span>
                <form method="POST" action="{{ route('client.domains.theft-protection', $domain) }}">
                  @csrf
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" @checked($theftStatus) onchange="this.form.submit()">
                  </div>
                </form>
              </div>
            </div>
          @endif
        </div>

        @if ($supportsForwarding)
          <div class="mt-4 pt-4 border-top">
            <h3 class="fw-semibold text-dark mb-1" style="font-size:14px">Domain Forwarding</h3>
            <p class="text-muted mb-2" style="font-size:11px">Arahkan domain ini ke alamat website lain (redirect), tanpa perlu hosting terpisah.</p>
            <form method="POST" action="{{ route('client.domains.forwarding', $domain) }}" class="d-flex gap-2">
              @csrf
              <input type="url" name="forward_to" value="{{ $forwardTo }}" placeholder="https://contoh.com (kosongkan untuk matikan)" class="form-control form-control-sm">
              <button type="submit" class="btn btn-outline-secondary btn-sm flex-shrink-0">Simpan</button>
            </form>
          </div>
        @endif

        @if ($supportsEmailForwarding)
          <div class="mt-4 pt-4 border-top">
            <a href="{{ route('client.domains.email-forwarding', $domain) }}" class="btn btn-outline-secondary">
              <i class="fa-solid fa-envelope" style="font-size:11px"></i> Kelola Email Forwarding
            </a>
          </div>
        @endif

        {{-- Kelola DNS & kode transfer -- HANYA untuk domain yang sudah
             benar-benar terdaftar di registrar (provision_status
             'registered'). Sebelumnya kedua tombol ini tampil tanpa
             syarat, termasuk untuk domain yang masih 'pending'
             (belum dibayar / belum diproses) -- padahal DNS-nya belum
             ada di registrar mana pun untuk dikelola, dan kode transfer
             tidak masuk akal diminta untuk domain yang belum pernah
             terdaftar. --}}
        @if ($domain->provision_status === 'registered')
          <div class="mt-4 pt-4 border-top d-flex flex-wrap gap-3">
            <a href="{{ route('client.domains.dns', $domain) }}" class="btn btn-outline-secondary">
              <i class="fa-solid fa-server" style="font-size:11px"></i> Kelola DNS
            </a>
            <form method="POST" action="{{ route('client.domains.auth-code', $domain) }}">
              @csrf
              <button type="submit" class="btn btn-outline-secondary">
                <i class="fa-solid fa-key" style="font-size:11px"></i> Ajukan Permintaan Kode Transfer (EPP)
              </button>
            </form>
          </div>
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Kode transfer tidak diberikan langsung — permintaan akan ditinjau tim kami lewat tiket, lalu dikirim ke email Anda setelah disetujui.
          </p>
        @else
          <div class="mt-4 pt-4 border-top">
            <p class="text-muted mb-0" style="font-size:12px">
              <i class="fa-solid fa-circle-info"></i>
              Pengelolaan DNS dan permintaan kode transfer tersedia setelah domain ini berhasil didaftarkan.
              @if ($domain->status === 'pending')
                Status saat ini: menunggu pembayaran/pemrosesan.
              @endif
            </p>
          </div>
        @endif

        {{-- Ubah nameserver --}}
        <div class="mt-4 pt-4 border-top">
          <h3 class="fw-semibold text-dark mb-1" style="font-size:14px">Nameserver</h3>
          <p class="text-muted mb-3" style="font-size:12px">
            Arahkan domain ke server hosting mana pun. Isi minimal dua nameserver.
          </p>

          @if ($domain->status !== 'active')
            <p class="text-muted mb-0" style="font-size:14px">
              Nameserver hanya bisa diubah untuk domain berstatus aktif.
            </p>
          @elseif (! $domain->registrar_id)
            <p class="text-muted mb-0" style="font-size:14px">
              Domain ini belum terhubung ke registrar. Silakan hubungi support untuk mengubah nameserver.
            </p>
          @else
            @php $ns = $domain->nameservers ?? []; @endphp

            <form method="POST" action="{{ route('client.domains.nameservers', $domain) }}" class="d-flex flex-column gap-2">
              @csrf

              @for ($i = 0; $i < 4; $i++)
                <input type="text" name="nameservers[]"
                       value="{{ $errors->has('nameservers') || $errors->has('nameservers.*') ? old('nameservers.' . $i, $ns[$i] ?? '') : ($ns[$i] ?? '') }}"
                       placeholder="ns{{ $i + 1 }}.contoh.com{{ $i >= 2 ? ' (opsional)' : '' }}"
                       class="form-control form-control-sm" style="font-family:monospace" {{ $i < 2 ? 'required' : '' }}>
              @endfor

              @error('nameservers') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
              @error('nameservers.*') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror

              <button type="submit" class="btn btn-theme mt-1" style="width:fit-content">
                <i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Nameserver
              </button>

              <p class="text-muted mb-0" style="font-size:11px">
                Perubahan DNS bisa memakan waktu hingga 24 jam untuk menyebar ke seluruh dunia.
              </p>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-2">Bantuan</h2>
        <p class="text-muted mb-3" style="font-size:14px">Butuh perpanjang domain atau ubah data WHOIS?</p>
        <a href="{{ route('client.tickets.create') }}" class="btn btn-theme w-100">
          <i class="fa-solid fa-headset" style="font-size:11px"></i> Hubungi Support
        </a>
      </div>
    </div>
  </div>
@endsection
