@extends('client.layout')
@section('title', 'Berkas Persyaratan')
@section('content')

  <div class="mb-4">
    <a href="{{ route('client.domains') }}" class="text-decoration-none text-muted" style="font-size:12px">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Domain
    </a>
    <h1 class="h4 fw-bold text-dark mt-1 mb-1">Berkas Persyaratan — {{ $domain->domain_name }}</h1>
    <p class="small text-muted mb-0">
      Domain <b>.{{ $tldExt }}</b> mewajibkan berkas berikut sebelum bisa didaftarkan.
      Unggah satu per satu, lalu tunggu tim kami memverifikasi.
    </p>
  </div>

  @if ($requirements->isEmpty())
    <div class="card border rounded-4 p-5 text-center">
      <i class="fa-solid fa-circle-check text-success mb-3" style="font-size:1.75rem"></i>
      <p class="fw-medium text-dark mb-1">Domain ini tidak butuh berkas apa pun</p>
      <p class="text-muted mb-0" style="font-size:13px">Pendaftarannya bisa langsung diproses.</p>
    </div>
  @else
    {{-- Ringkasan progres -- klien perlu tahu persis apa yang masih
         ditunggu, bukan cuma "belum lengkap". --}}
    <div class="card border rounded-4 p-4 mb-3"
         style="{{ $progress['complete'] ? 'border-color:#a7f3d0!important;background:rgba(16,185,129,.04)' : ($progress['rejected'] > 0 ? 'border-color:#fecaca!important;background:rgba(239,68,68,.04)' : '') }}">
      @if ($progress['complete'])
        <p class="fw-bold text-dark mb-1" style="font-size:14px">
          <i class="fa-solid fa-circle-check text-success"></i> Semua berkas sudah disetujui
        </p>
        <p class="text-muted mb-2" style="font-size:12px">
          Domain akan didaftarkan setelah pembayaran diterima.
        </p>

        @if ($invoice)
          <a href="{{ route('client.invoices.show', $invoice) }}" class="btn btn-theme btn-sm">
            <i class="fa-solid fa-credit-card" style="font-size:11px"></i>
            Lanjut Bayar — {{ $invoice->invoice_number }} (Rp {{ number_format($invoice->total, 0, ',', '.') }})
          </a>
        @else
          {{-- Tidak ada invoice menunggu: entah sudah dibayar, atau domain
               ini memang tidak punya tagihan terbuka. Diarahkan ke daftar
               invoice supaya klien tidak menebak-nebak sendiri. --}}
          <a href="{{ route('client.invoices') }}" class="btn btn-outline-secondary btn-sm">
            Lihat Invoice Saya
          </a>
        @endif
      @elseif ($progress['rejected'] > 0)
        <p class="fw-bold mb-1" style="font-size:14px;color:#991b1b">
          <i class="fa-solid fa-triangle-exclamation"></i> Ada berkas yang perlu diunggah ulang
        </p>
        <p class="text-muted mb-0" style="font-size:12px">
          {{ $progress['rejected'] }} berkas ditolak. Baca alasannya di bawah, perbaiki, lalu unggah ulang.
        </p>
      @else
        <p class="fw-bold text-dark mb-1" style="font-size:14px">
          Menunggu kelengkapan berkas — {{ $progress['approved'] }}/{{ $progress['required'] }} berkas wajib disetujui
        </p>
        <p class="text-muted mb-0" style="font-size:12px">
          {{-- Dipakai angka BLOCKING (wajib + opsional yang sudah
               diunggah), bukan angka wajib saja -- kalau tidak, berkas
               opsional yang masih ditinjau (mis. Sertifikat Merek) tidak
               pernah disebut di sini, padahal itu alasan sebenarnya
               pembayaran masih tertahan. --}}
          @if ($progress['blocking_missing'] > 0)
            {{ $progress['blocking_missing'] }} berkas wajib belum diunggah.
          @endif
          @php
            // Syarat opsional yang belum disentuh sama sekali -- TIDAK
            // menghalangi pembayaran (memang boleh dilewati), tapi
            // tetap disebut di sini supaya kalimatnya konsisten dengan
            // tabel di bawah yang menampilkan SEMUA syarat (termasuk
            // opsional), bukan cuma yang wajib.
            $opsionalBelum = $progress['items']->filter(fn ($i) => ! $i['requirement']->is_required && $i['status'] === 'missing')->count();
          @endphp
          @if ($opsionalBelum > 0)
            {{ $opsionalBelum }} berkas opsional belum diunggah (boleh dilewati).
          @endif
          @if ($progress['blocking_pending'] > 0)
            {{ $progress['blocking_pending'] }} berkas sedang ditinjau tim kami{{ $progress['pending'] < $progress['blocking_pending'] ? ' (termasuk berkas opsional yang sudah diunggah)' : '' }}.
          @endif
          @if ($progress['blocking_rejected'] > 0)
            {{ $progress['blocking_rejected'] }} berkas ditolak dan perlu diunggah ulang.
          @endif
          Pembayaran baru bisa dilanjutkan setelah semuanya disetujui.
        </p>
      @endif
    </div>

    <div class="d-flex flex-column gap-3">
      @foreach ($progress['items'] as $item)
        @php
          $req = $item['requirement'];
          $doc = $item['document'];
          $st = $item['status'];
        @endphp

        <div class="card border rounded-4 p-4"
             style="{{ $st === 'rejected' ? 'border-color:#fecaca!important' : ($st === 'approved' ? 'border-color:#a7f3d0!important' : '') }}">
          <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-2">
            <div class="min-w-0">
              <p class="fw-bold text-dark mb-1" style="font-size:14px">
                {{ $req->name }}
                @unless ($req->is_required)
                  <span class="badge badge-soft-secondary" style="font-size:9px">opsional</span>
                @endunless
              </p>
              @if ($req->description)
                <p class="text-muted mb-0" style="font-size:12px">{{ $req->description }}</p>
              @endif
            </div>

            @php
              $badge = match ($st) {
                'approved' => ['Disetujui', '#d1fae5', '#047857'],
                'rejected' => ['Ditolak', '#fee2e2', '#991b1b'],
                'pending'  => ['Sedang ditinjau', '#fef3c7', '#b45309'],
                default    => ['Belum diunggah', '#f1f5f9', '#64748b'],
              };
            @endphp
            <span class="badge flex-shrink-0" style="font-size:10px;background:{{ $badge[1] }};color:{{ $badge[2] }}">{{ $badge[0] }}</span>
          </div>

          @if ($doc)
            <div class="d-flex align-items-center gap-2 rounded-3 px-3 py-2 mb-2" style="background:#f8fafc">
              <i class="fa-regular fa-file text-muted" style="font-size:12px"></i>
              <a href="{{ route('client.domains.documents.file', $doc) }}" target="_blank"
                 class="text-decoration-none text-dark text-truncate" style="font-size:13px">{{ $doc->original_name }}</a>
              <span class="text-muted ms-auto flex-shrink-0" style="font-size:11px">{{ $doc->created_at->format('d M Y') }}</span>
            </div>
          @endif

          @if ($st === 'rejected' && $doc?->admin_note)
            <div class="rounded-3 px-3 py-2 mb-2" style="background:#fef2f2;border:1px solid #fecaca">
              <p class="mb-0" style="font-size:12px;color:#991b1b">
                <b>Alasan ditolak:</b> {{ $doc->admin_note }}
              </p>
            </div>
          @endif

          @if ($st !== 'approved')
            <form method="POST" action="{{ route('client.domains.documents.upload', $domain) }}"
                  enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-start">
              @csrf
              <input type="hidden" name="document_requirement_id" value="{{ $req->id }}">
              <input type="file" name="file" required accept=".zip,.rar,.pdf,.jpg,.jpeg,.png"
                     class="form-control form-control-sm" style="max-width:20rem">
              <button type="submit" class="btn btn-theme btn-sm">
                <i class="fa-solid fa-upload" style="font-size:11px"></i>
                {{ $doc ? 'Unggah Ulang' : 'Unggah' }}
              </button>
            </form>
            <p class="text-muted mt-2 mb-0" style="font-size:11px">
              Format: ZIP, RAR, PDF, JPG, PNG &middot; maksimal 2 MB.
            </p>
          @endif
        </div>
      @endforeach
    </div>
  @endif

@endsection
