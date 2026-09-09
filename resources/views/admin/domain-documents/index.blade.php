@extends('layouts.admin')
@section('title', 'Verifikasi Berkas Domain')
@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Verifikasi Berkas Domain</h1>
    <p class="small text-muted mb-0">
      Domain yang ekstensinya butuh berkas persyaratan. Klien baru bisa lanjut membayar
      setelah semua berkas wajib disetujui.
    </p>
  </div>

  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    @foreach (['waiting' => 'Menunggu Verifikasi', 'rejected' => 'Ada yang Ditolak', 'complete' => 'Sudah Lengkap', 'all' => 'Semua'] as $key => $label)
      <a href="{{ route('admin.domain-documents.index', ['status' => $key, 'search' => request('search')]) }}"
         class="px-3 py-2 rounded-3 text-decoration-none {{ $status === $key ? 'text-white' : 'text-muted border' }}"
         style="font-size:12px;{{ $status === $key ? 'background:#4f46e5' : '' }}">
        {{ $label }}
      </a>
    @endforeach

    <form method="GET" class="d-flex gap-2 ms-auto">
      <input type="hidden" name="status" value="{{ $status }}">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari domain / klien..." class="form-control form-control-sm" style="width:14rem">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Cari</button>
    </form>
  </div>

  @forelse ($domains as $domain)
    @php $p = $progress[$domain->id]; @endphp

    <div class="card border rounded-4 overflow-hidden mb-3">
      <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2"
           style="background:{{ $p['complete'] ? 'rgba(16,185,129,.04)' : ($p['rejected'] > 0 ? 'rgba(239,68,68,.04)' : '#f8fafc') }}">
        <div class="min-w-0">
          <p class="fw-bold text-dark mb-0" style="font-size:14px">
            {{ $domain->domain_name }}
            <span class="badge {{ $p['complete'] ? 'badge-soft-success' : 'badge-soft-secondary' }}" style="font-size:9px">
              {{ $p['approved'] }}/{{ $p['required'] }} wajib disetujui
              @php $opsional = $p['items']->count() - $p['required']; @endphp
              @if ($opsional > 0)
                <span class="text-muted">(+{{ $opsional }} opsional)</span>
              @endif
            </span>
          </p>
          <p class="text-muted mb-0" style="font-size:11px">
            {{ $domain->client->name ?? '—' }} &middot; {{ $domain->client->email ?? '—' }}
            &middot; ID Klien #{{ $domain->client_id }}
          </p>
        </div>

        <div class="d-flex align-items-center gap-2">
          {{-- Tidak ada lagi tombol "Tandai Lengkap". Klien otomatis bisa
               lanjut membayar begitu berkas terakhir di-approve -- tidak
               perlu langkah konfirmasi tambahan yang cuma membingungkan
               (dulu gerbangnya terbuka dari dua pintu sekaligus, jadi
               tombolnya sering terasa tidak berpengaruh apa-apa). --}}
          @if ($p['complete'])
            <span class="badge badge-soft-success" style="font-size:10px">
              <i class="fa-solid fa-check"></i> Lengkap — klien bisa lanjut bayar
            </span>
          @else
            <span class="badge badge-soft-secondary" style="font-size:10px">
              Menunggu {{ $p['items']->where('status', '!=', 'approved')->count() }} berkas
            </span>
          @endif
          <a href="{{ route('admin.domains.details', $domain) }}" class="btn btn-outline-secondary btn-sm">Detail Domain</a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:13px">
          <thead>
            <tr class="small text-uppercase text-muted" style="background:#f8fafc">
              <th class="px-4 py-2">Persyaratan</th>
              <th class="py-2">Berkas</th>
              <th class="text-center py-2">Status</th>
              <th class="text-end px-4 py-2">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($p['items'] as $item)
              @php $doc = $item['document']; @endphp
              <tr>
                <td class="px-4 py-2">
                  <span class="fw-medium text-dark">{{ $item['requirement']->name }}</span>
                  @unless ($item['requirement']->is_required)
                    <span class="badge badge-soft-secondary" style="font-size:9px">opsional</span>
                  @endunless
                </td>
                <td class="py-2">
                  @if ($doc)
                    <a href="{{ route('admin.domain-documents.file', $doc) }}" target="_blank" class="text-accent text-decoration-none">
                      <i class="fa-regular fa-file" style="font-size:11px"></i> {{ Str::limit($doc->original_name, 32) }}
                    </a>
                    @if ($doc->admin_note)
                      <span class="d-block text-muted" style="font-size:10px">Catatan: {{ $doc->admin_note }}</span>
                    @endif
                  @else
                    <span class="text-muted" style="font-size:12px">Belum diunggah</span>
                  @endif
                </td>
                <td class="text-center py-2">
                  @php
                    $badge = match ($item['status']) {
                      'approved' => ['Disetujui', '#d1fae5', '#047857'],
                      'rejected' => ['Ditolak', '#fee2e2', '#991b1b'],
                      'pending'  => ['Menunggu', '#fef3c7', '#b45309'],
                      default    => ['Belum ada', '#f1f5f9', '#64748b'],
                    };
                  @endphp
                  <span class="badge" style="font-size:10px;background:{{ $badge[1] }};color:{{ $badge[2] }}">{{ $badge[0] }}</span>
                </td>
                <td class="text-end px-4 py-2">
                  @if ($doc && $item['status'] !== 'approved')
                    <div class="d-flex align-items-center justify-content-end gap-2">
                      <form method="POST" action="{{ route('admin.domain-documents.review', $doc) }}">
                        @csrf
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="btn btn-outline-success btn-sm" style="font-size:11px">
                          <i class="fa-solid fa-check"></i> Verifikasi
                        </button>
                      </form>
                      <button type="button" class="btn btn-outline-danger btn-sm" style="font-size:11px"
                              onclick="tolakBerkas({{ $doc->id }})">
                        <i class="fa-solid fa-xmark"></i> Tolak
                      </button>
                    </div>

                    <form method="POST" action="{{ route('admin.domain-documents.review', $doc) }}"
                          id="tolak-{{ $doc->id }}" class="d-none mt-2">
                      @csrf
                      <input type="hidden" name="status" value="rejected">
                      <div class="d-flex gap-2 justify-content-end">
                        <input type="text" name="admin_note" required maxlength="500"
                               placeholder="Alasan ditolak (dibaca klien)" class="form-control form-control-sm" style="max-width:18rem">
                        <button type="submit" class="btn btn-danger btn-sm" style="font-size:11px">Kirim</button>
                      </div>
                    </form>
                  @elseif ($item['status'] === 'approved')
                    <span class="text-muted" style="font-size:11px">—</span>
                  @else
                    <span class="text-muted" style="font-size:11px">Menunggu klien</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @empty
    <div class="card border rounded-4 p-5 text-center">
      <i class="fa-solid fa-inbox text-muted mb-3" style="font-size:1.75rem"></i>
      <p class="text-muted mb-0" style="font-size:14px">Tidak ada domain pada filter ini.</p>
    </div>
  @endforelse

  @if ($domains->hasPages())
    <div class="mt-3">{{ $domains->links('pagination.bootstrap') }}</div>
  @endif

  <script>
    // Form alasan penolakan disembunyikan sampai dibutuhkan -- alasan
    // WAJIB diisi karena teks inilah yang dibaca klien untuk tahu apa
    // yang harus diperbaiki saat mengunggah ulang.
    function tolakBerkas(id) {
      const form = document.getElementById('tolak-' + id);
      form.classList.toggle('d-none');
      form.querySelector('input[name="admin_note"]').focus();
    }
  </script>
@endsection
