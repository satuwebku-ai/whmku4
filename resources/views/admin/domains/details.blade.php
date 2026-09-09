@extends('layouts.admin')

@section('title', 'Detail Domain — ' . $domain->domain_name)

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <a href="{{ route('admin.domains') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Domain</a>
      <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $domain->domain_name }}</h1>
    </div>
    @php
      $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'suspended' => 'badge-soft-danger', 'cancelled' => 'badge-soft-secondary'];
      $displayStatus = $domain->status === 'expired' ? 'suspended' : $domain->status;
    @endphp
    <span class="badge {{ $badgeMap[$displayStatus] ?? 'badge-soft-secondary' }}" style="font-size:13px;padding:.4rem .8rem">{{ ucfirst($domain->status) }}</span>
  </div>

  {{-- Dokumen persyaratan --}}
  @if ($domain->provision_status === 'needs_documents' || $domain->documents->isNotEmpty())
    <div class="card border rounded-4 p-4 mb-3 {{ $domain->provision_status === 'needs_documents' ? 'border-warning bg-warning bg-opacity-10' : '' }}">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <h2 class="small fw-bold mb-0 {{ $domain->provision_status === 'needs_documents' ? 'text-warning' : 'text-dark' }}">
          <i class="fa-solid fa-file-lines"></i> Dokumen Persyaratan Domain
        </h2>
        @if ($domain->provision_status === 'needs_documents')
          <form method="POST" action="{{ route('admin.domains.verify-documents', $domain) }}"
                data-confirm="Tandai dokumen untuk &quot;{{ $domain->domain_name }}&quot; sudah lengkap dan lanjutkan pendaftaran?"
                data-confirm-title="Dokumen Lengkap?" data-confirm-style="info" data-confirm-label="Ya, Lanjutkan">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Dokumen Lengkap, Lanjutkan</button>
          </form>
        @endif
      </div>

      @forelse ($domain->documents as $doc)
        <div class="d-flex align-items-center justify-content-between py-2 border-bottom flex-wrap gap-2">
          <a href="{{ route('admin.domain-documents.file', $doc) }}" target="_blank" class="small text-dark text-decoration-none d-flex align-items-center gap-2 min-w-0">
            <i class="fa-solid fa-file text-muted flex-shrink-0"></i>
            <span class="text-truncate">{{ $doc->original_name }}</span>
          </a>
          <form method="POST" action="{{ route('admin.domain-documents.review', $doc) }}" class="d-flex align-items-center gap-2 flex-shrink-0">
            @csrf
            <select name="status" class="form-select" style="padding:.2rem .5rem;font-size:.8rem;border-radius:.375rem">
              <option value="pending" @selected($doc->status === 'pending')>Menunggu</option>
              <option value="approved" @selected($doc->status === 'approved')>Setuju</option>
              <option value="rejected" @selected($doc->status === 'rejected')>Tolak</option>
            </select>
            <input type="text" name="admin_note" value="{{ $doc->admin_note }}" placeholder="Catatan (kalau ditolak)" class="form-control form-control-sm" style="width:10rem">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Simpan</button>
          </form>
        </div>
      @empty
        <p class="small text-muted mb-0">Klien belum mengunggah dokumen apa pun.</p>
      @endforelse

      @if ($domain->documents->where('status', '!=', 'replaced')->isNotEmpty())
        <details class="mt-3 pt-3 border-top">
          <summary class="small fw-medium text-accent" style="cursor:pointer">
            <i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Dokumen ke Registrar
          </summary>
          <form method="POST" action="{{ route('admin.domains.send-documents', $domain) }}" class="row g-2 align-items-end mt-2">
            @csrf
            <div class="col-sm-5">
              <label class="text-muted mb-1 d-block" style="font-size:11px">Email Tujuan</label>
              <input type="email" name="recipient_email" value="{{ $domain->registrar->documents_email ?? '' }}"
                     placeholder="verifikasi@registrar.com" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-5">
              <label class="text-muted mb-1 d-block" style="font-size:11px">Catatan (opsional)</label>
              <input type="text" name="note" placeholder="mis. mohon diproses segera" class="form-control form-control-sm">
            </div>
            <div class="col-sm-2">
              <button type="submit" class="btn btn-outline-primary btn-sm w-100">Kirim</button>
            </div>
          </form>
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Mengirim {{ $domain->documents->where('status', '!=', 'replaced')->count() }} berkas yang sedang berlaku
            (bukan versi lama yang sudah diganti) sebagai lampiran email.
            @if ($domain->registrar?->documents_email)
              Email tujuan terisi otomatis dari pengaturan registrar {{ $domain->registrar->name }} — boleh diganti manual di atas.
            @else
              Belum ada email bawaan untuk registrar ini — atur di halaman Registrar supaya terisi otomatis lain kali.
            @endif
          </p>
        </details>
      @endif
    </div>
  @endif

  {{-- Transfer pending --}}
  @if ($domain->is_transfer && $domain->provision_status === 'transfer_pending')
    <div class="card border border-warning bg-warning bg-opacity-10 rounded-4 p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <p class="small text-warning mb-0">
          <i class="fa-solid fa-clock"></i>
          Permintaan transfer sudah dikirim, menunggu persetujuan pemilik domain di registrar lama
          (biasanya 5–7 hari). Cek status di dashboard Liqu.id, lalu konfirmasi di sini kalau sudah selesai.
        </p>
        <form method="POST" action="{{ route('admin.domains.transfer-complete', $domain) }}"
              data-confirm="Konfirmasi transfer &quot;{{ $domain->domain_name }}&quot; sudah benar-benar selesai di Liqu.id?"
              data-confirm-title="Konfirmasi Transfer Selesai" data-confirm-style="info" data-confirm-label="Ya, Sudah Selesai">
          @csrf
          <button type="submit" class="btn btn-primary btn-sm flex-shrink-0"><i class="fa-solid fa-check" style="font-size:11px"></i> Tandai Transfer Selesai</button>
        </form>
      </div>
    </div>
  @endif

  {{-- Expired --}}
  @if ($domain->status === 'expired' && $domain->registrar)
    <div class="card border border-danger bg-danger bg-opacity-10 rounded-4 p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <p class="small text-danger mb-0">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Domain ini sudah kedaluwarsa. Masih mungkin dipulihkan lewat masa tenggang registrar
          (biasanya ~30 hari sejak kedaluwarsa, beda-beda tiap TLD) — ada biaya tambahan dari registrar.
        </p>
        <form method="POST" action="{{ route('admin.domains.restore', $domain) }}"
              data-confirm="Coba pulihkan &quot;{{ $domain->domain_name }}&quot; dari masa tenggang? Registrar mungkin mengenakan biaya tambahan."
              data-confirm-title="Pulihkan Domain" data-confirm-style="warn" data-confirm-label="Ya, Coba Pulihkan">
          @csrf
          <button type="submit" class="btn btn-danger btn-sm flex-shrink-0"><i class="fa-solid fa-rotate-left" style="font-size:11px"></i> Coba Pulihkan</button>
        </form>
      </div>
    </div>
  @endif

  {{-- Registrasi gagal --}}
  @if ($domain->provision_status === 'failed')
    <div class="card border border-danger bg-danger bg-opacity-10 rounded-4 p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div>
          <p class="small text-danger fw-medium mb-0"><i class="fa-solid fa-circle-exclamation"></i> Pendaftaran domain gagal</p>
          <p class="text-danger mb-0 mt-1" style="font-size:12px">{{ $domain->provision_message }}</p>
        </div>
        <form method="POST" action="{{ route('admin.domains.retry', $domain) }}"
              data-confirm="Coba daftarkan &quot;{{ $domain->domain_name }}&quot; lagi sekarang?" data-confirm-title="Coba Ulang" data-confirm-style="info" data-confirm-label="Ya, Coba Lagi">
          @csrf
          <button type="submit" class="btn btn-danger btn-sm flex-shrink-0"><i class="fa-solid fa-rotate-right" style="font-size:11px"></i> Coba Daftarkan Ulang</button>
        </form>
      </div>
    </div>
  @endif

  {{-- Butuh data eligibility --}}
  @if ($domain->provision_status === 'needs_eligibility')
    @php
      $tldExt = ltrim($domain->tld?->extension ?? '', '.');
      $hasExample = in_array($tldExt, ['us', 'asia']);
      $exampleValue = ['us' => 'us_purpose=business&us_category=citizen', 'asia' => 'asia_contact_id=0'][$tldExt] ?? null;
    @endphp
    <div class="card border border-warning bg-warning bg-opacity-10 rounded-4 p-4 mb-3">
      <h2 class="small fw-bold text-warning mb-1"><i class="fa-solid fa-clipboard-list"></i> Butuh Data Kelayakan (Eligibility)</h2>
      <p class="small text-warning mb-3">
        Domain <b>.{{ $tldExt }}</b> mewajibkan data kelayakan tambahan dari registry aslinya sebelum bisa
        didaftarkan — belum otomatis diproses, menunggu diisi di sini.
        @if (! $hasExample)
          <br><b>Perhatian:</b> saya tidak punya format resmi untuk <code>.{{ $tldExt }}</code> —
          cek dulu di dashboard Liqu.id atau tanya support mereka sebelum mengisi, supaya tidak salah format.
        @endif
      </p>
      <form method="POST" action="{{ route('admin.domains.eligibility', $domain) }}" class="row g-2">
        @csrf
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Eligibility Criteria</label>
          <input type="text" name="eligibility_criteria" value="{{ old('eligibility_criteria', $hasExample ? $tldExt : '') }}" class="form-control form-control-sm" placeholder="{{ $tldExt }}">
        </div>
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Extra Data</label>
          <input type="text" name="eligibility_extra" value="{{ old('eligibility_extra') }}" class="form-control form-control-sm" placeholder="{{ $exampleValue ?? 'format sesuai TLD, cek dokumentasi Liqu.id' }}">
        </div>
        @error('eligibility_criteria') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        @error('eligibility_extra') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <div class="col-12">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan &amp; Coba Daftarkan</button>
        </div>
      </form>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-12 col-lg-8">

      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-3">Informasi Domain</h2>
        <div class="row g-3 small">
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">KLIEN</p>
            <p class="fw-medium text-dark mb-0">{{ $domain->client->name ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">REGISTRAR</p>
            <p class="fw-medium text-dark mb-0">{{ $domain->registrar->name ?? 'Manual' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">TANGGAL REGISTER</p>
            <p class="fw-medium text-dark mb-0">{{ $domain->register_date?->format('d M Y') ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">JATUH TEMPO</p>
            <p class="fw-medium text-dark mb-0">{{ $domain->expiry_date?->format('d M Y') ?? '—' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">AUTO RENEW</p>
            <p class="fw-medium text-dark mb-0">{{ $domain->auto_renew ? 'Ya' : 'Tidak' }}</p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">WHOIS PRIVACY</p>
            <p class="fw-medium text-dark mb-0">
              {{ $domain->hasActivePrivacy() ? 'Aktif' : 'Nonaktif' }}
              @if ($domain->privacy_expires_at)
                <span class="text-muted fw-normal" style="font-size:11px">(s.d. {{ $domain->privacy_expires_at->format('d M Y') }})</span>
              @endif
              @if ($domain->privacy_invoice_id)
                <span class="badge badge-soft-warning" style="font-size:10px">Menunggu bayar</span>
              @endif
              @if (! is_null($privacyAtRegistrar) && $privacyAtRegistrar !== $domain->hasActivePrivacy())
                <p class="text-danger fw-normal mb-0 mt-1" style="font-size:11px">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                  Di registrar: <b>{{ $privacyAtRegistrar ? 'Aktif' : 'Nonaktif' }}</b> — tidak cocok.
                  @if ($privacyAtRegistrar)
                    Kita masih ditagih registrar padahal klien tidak membayar.
                  @else
                    Klien sudah bayar tapi belum aktif di registrar.
                  @endif
                </p>
              @endif
            </p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">NAMESERVER</p>
            <p class="fw-medium text-dark mb-0">
              @if (! empty($domain->nameservers))
                @foreach ($domain->nameservers as $ns)
                  <span class="d-block" style="font-family:monospace">{{ $ns }}</span>
                @endforeach
              @else
                <span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Belum diatur</span>
                @if ($domain->registrar && $domain->registrar->default_ns1)
                  <form method="POST" action="{{ route('admin.domains.apply-default-ns', $domain) }}" class="mt-1"
                        data-confirm="Terapkan nameserver default ({{ $domain->registrar->default_ns1 }}) ke domain ini?" data-confirm-title="Terapkan Nameserver Default" data-confirm-style="info" data-confirm-label="Ya, Terapkan">
                    @csrf
                    <button type="submit" class="btn btn-link p-0 text-accent" style="font-size:12px">Terapkan Nameserver Default</button>
                  </form>
                @endif
              @endif
            </p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted mb-1" style="font-size:11px">ORDER TERKAIT</p>
            <p class="fw-medium text-dark mb-0">
              @if ($domain->order)
                <a href="{{ route('admin.orders.details', $domain->order) }}" class="text-decoration-none text-accent">#{{ $domain->order->order_number }}</a>
              @else
                —
              @endif
            </p>
          </div>
        </div>

        @if ($domain->provision_message)
          <div class="mt-3 pt-3 border-top small">
            <span class="text-muted" style="font-size:11px">STATUS REGISTRASI TERAKHIR</span>
            <p class="mb-0 mt-1 {{ $domain->provision_status === 'registered' ? 'text-success' : 'text-danger' }}">{{ $domain->provision_message }}</p>
          </div>
        @endif
      </div>

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Catatan Internal</h2>
        <form method="POST" action="{{ route('admin.domain.notes') }}">
          @csrf
          <input type="hidden" name="domain_id" value="{{ $domain->id }}">
          <textarea name="internal_notes" rows="4" class="form-control form-control-sm" placeholder="Catatan staf tentang domain ini...">{{ old('internal_notes', $domain->internal_notes) }}</textarea>
          <button type="submit" class="btn btn-outline-secondary btn-sm mt-2"><i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Catatan</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Aksi</h2>
        <div class="d-flex flex-column gap-2">
          @if ($domain->registrar)
            <form method="POST" action="{{ route('admin.domains.renew', $domain) }}" data-confirm="Perpanjang domain ini 1 tahun via registrar?" data-confirm-title="Perpanjang Domain" data-confirm-style="info" data-confirm-label="Ya, Perpanjang">
              @csrf
              <button type="submit" class="btn btn-primary btn-sm w-100 text-start"><i class="fa-solid fa-rotate" style="font-size:11px"></i> Perpanjang 1 Tahun</button>
            </form>
          @endif
          @if ($domain->status !== 'cancelled')
            <form method="POST" action="{{ route('admin.domain.cancel') }}" data-confirm="Batalkan domain ini?" data-confirm-title="Batalkan" data-confirm-style="warn" data-confirm-label="Ya, Batalkan">
              @csrf
              <input type="hidden" name="domain_id" value="{{ $domain->id }}">
              <button type="submit" class="btn btn-outline-danger btn-sm w-100 text-start"><i class="fa-solid fa-xmark" style="font-size:11px"></i> Batalkan</button>
            </form>
          @endif
          <a href="{{ route('admin.domain.edit.page', $domain) }}" class="btn btn-outline-secondary btn-sm w-100 text-start">
            <i class="fa-regular fa-pen-to-square" style="font-size:11px"></i> Edit Data
          </a>
        </div>
      </div>
    </div>
  </div>

@endsection
