{{-- Panel tab "Domain Premium Custom" — tabel nama domain dengan harga masing-masing. --}}
<div class="card-public p-4 mb-4" id="custom-premium">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h2 class="h6 fw-bold text-dark mb-1">Daftar Harga Domain Premium Custom</h2>
      <p class="text-muted mb-0" style="font-size:12px;max-width:36rem">
        Nama domain pilihan dengan harga masing-masing. Setiap nama hanya satu — siapa cepat, dia dapat.
        Harga untuk registrasi 1 tahun.
      </p>
    </div>

    @if ($customTotal > 0 || $customQuery !== '')
      <form method="GET" action="{{ route('domain-premium.index') }}#custom-premium" class="d-flex flex-wrap gap-2">
        <input type="text" name="cari" value="{{ $customQuery }}" placeholder="Cari nama…" class="form-control form-control-sm" style="width:11rem">
        <select name="urut" class="form-select form-select-sm" style="width:9rem" onchange="this.form.submit()">
          <option value="karakter" @selected($customSort === 'karakter')>Karakter tersedikit</option>
          <option value="murah" @selected($customSort === 'murah')>Termurah</option>
          <option value="mahal" @selected($customSort === 'mahal')>Termahal</option>
        </select>
        <button class="btn btn-sm btn-theme" aria-label="Cari"><i class="fa-solid fa-magnifying-glass" style="font-size:11px"></i></button>
      </form>
    @endif
  </div>

  @if ($customTotal === 0 && $customQuery === '')
    <p class="text-muted mb-0" style="font-size:13px">Belum ada domain premium custom yang dijual saat ini. Silakan cek kembali nanti.</p>
  @elseif ($customDomains->isEmpty())
    <p class="text-muted mb-0" style="font-size:13px">Tidak ada domain yang cocok dengan pencarian Anda.</p>
  @else
    <div style="overflow-x:auto">
      <table class="table align-middle mb-0" style="font-size:13px">
        <thead>
          <tr class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em">
            <th>Domain</th>
            <th class="text-center">Karakter</th>
            <th>Usia</th>
            <th class="text-end">Harga / tahun</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($customDomains as $cd)
            <tr>
              <td class="fw-semibold text-dark">
                {{ $cd->domain_name }}
                <span class="badge ms-1" style="background:#fef3c7;color:#92400e;font-weight:600;font-size:10px">Premium</span>
              </td>
              <td class="text-center text-muted">{{ $cd->characters }}</td>
              <td class="text-muted">{{ $cd->age_label ?: '1 tahun' }}</td>
              <td class="text-end fw-semibold">Rp {{ number_format((float) $cd->sell_price, 0, ',', '.') }}</td>
              <td class="text-end">
                <form method="POST" action="{{ route('cart.add-custom-premium') }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="custom_premium_id" value="{{ $cd->id }}">
                  <button type="submit" class="btn btn-sm btn-theme"><i class="fa-solid fa-cart-plus" style="font-size:11px"></i> Pesan</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if ($customDomains->hasPages())
      <div class="d-flex align-items-center justify-content-between mt-3" style="font-size:13px">
        <span class="text-muted">Halaman {{ $customDomains->currentPage() }} dari {{ $customDomains->lastPage() }} · {{ number_format($customDomains->total(), 0, ',', '.') }} domain</span>
        <span>
          @if ($customDomains->previousPageUrl())<a href="{{ $customDomains->previousPageUrl() }}">‹ Sebelumnya</a>@endif
          @if ($customDomains->nextPageUrl())<a href="{{ $customDomains->nextPageUrl() }}" class="ms-3">Berikutnya ›</a>@endif
        </span>
      </div>
    @endif

    <p class="text-muted mt-3 mb-0" style="font-size:12px">
      Anda perlu masuk/daftar akun sebelum checkout. Dokumen persyaratan (KTP/NPWP dst.) sama seperti pendaftaran domain .id biasa,
      dan diminta setelah pesanan dibuat, sebelum invoice bisa dibayar.
    </p>
  @endif
</div>
