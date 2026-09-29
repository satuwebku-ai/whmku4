{{-- Panel tab "Domain Premium Custom" — tabel nama domain dengan harga masing-masing. --}}
<style>
  .pd-custom-table{table-layout:fixed;width:100%;font-size:13px;margin:0}
  .pd-custom-table thead th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:600;background:#f8fafc;padding:.7rem .75rem;border-bottom:1px solid #e5e7eb;white-space:nowrap}
  .pd-custom-table tbody td{padding:.7rem .75rem;vertical-align:middle;border-bottom:1px solid #eef0f3}
  .pd-custom-table tbody tr:last-child td{border-bottom:0}
  .pd-custom-table tbody tr:hover{background:#f8fbfb}
  .pd-custom-table .c-domain{width:38%}
  .pd-custom-table .c-chars{width:12%;text-align:center}
  .pd-custom-table .c-age{width:14%}
  .pd-custom-table .c-price{width:20%;text-align:right;white-space:nowrap}
  .pd-custom-table .c-act{width:16%;text-align:right}
  .pd-custom-table .dn{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;font-weight:600;color:#111827;overflow-wrap:anywhere}
  .pd-custom-table .dn-meta{display:none;font-size:11px;color:#6b7280;margin-top:2px}
  .pd-custom-table .price{font-weight:700;color:#111827}
  .pd-custom-table .btn-order{white-space:nowrap}
  .pd-custom-tools{display:flex;flex-wrap:wrap;gap:.5rem}
  @media (max-width: 767.98px){
    .pd-custom-table .c-chars,.pd-custom-table .c-age{display:none}
    .pd-custom-table .c-domain{width:44%}
    .pd-custom-table .c-price{width:30%}
    .pd-custom-table .c-act{width:26%}
    .pd-custom-table .dn-meta{display:block}
    .pd-custom-tools{width:100%}
    .pd-custom-tools input[name=cari]{flex:1 1 8rem;width:auto!important}
  }
</style>

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
      <form method="GET" action="{{ route('domain-premium.index') }}#custom-premium" class="pd-custom-tools">
        <input type="text" name="cari" value="{{ $customQuery }}" placeholder="Cari nama…" class="form-control form-control-sm" style="width:11rem">
        <select name="urut" class="form-select form-select-sm" style="width:10rem" onchange="this.form.submit()">
          <option value="karakter" @selected($customSort === 'karakter')>Karakter tersedikit</option>
          <option value="murah" @selected($customSort === 'murah')>Termurah</option>
          <option value="mahal" @selected($customSort === 'mahal')>Termahal</option>
        </select>
        <button class="btn btn-sm btn-theme" aria-label="Cari"><i class="fa-solid fa-magnifying-glass" style="font-size:11px"></i></button>
        @if ($customQuery !== '')
          <a href="{{ route('domain-premium.index') }}#custom-premium" class="btn btn-sm btn-outline-secondary">Reset</a>
        @endif
      </form>
    @endif
  </div>

  @if ($customTotal === 0 && $customQuery === '')
    <p class="text-muted mb-0" style="font-size:13px">Belum ada domain premium custom yang dijual saat ini. Silakan cek kembali nanti.</p>
  @elseif ($customDomains->isEmpty())
    <p class="text-muted mb-0" style="font-size:13px">Tidak ada domain yang cocok dengan pencarian Anda.</p>
  @else
    <div class="rounded-3 border overflow-hidden">
      <div style="overflow-x:auto">
        <table class="pd-custom-table">
          <thead>
            <tr>
              <th class="c-domain">Domain</th>
              <th class="c-chars">Karakter</th>
              <th class="c-age">Usia</th>
              <th class="c-price">Harga / tahun</th>
              <th class="c-act"><span class="visually-hidden">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($customDomains as $cd)
              <tr>
                <td class="c-domain">
                  <div class="dn">
                    <span>{{ $cd->domain_name }}</span>
                    <span class="badge" style="background:#fef3c7;color:#92400e;font-weight:600;font-size:10px">Premium</span>
                  </div>
                  <div class="dn-meta">{{ $cd->characters }} karakter · {{ $cd->age_label ?: '1 tahun' }}</div>
                </td>
                <td class="c-chars text-muted">{{ $cd->characters }}</td>
                <td class="c-age text-muted">{{ $cd->age_label ?: '1 tahun' }}</td>
                <td class="c-price price">Rp {{ number_format((float) $cd->sell_price, 0, ',', '.') }}</td>
                <td class="c-act">
                  <form method="POST" action="{{ route('cart.add-custom-premium') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="custom_premium_id" value="{{ $cd->id }}">
                    <button type="submit" class="btn btn-sm btn-theme btn-order"><i class="fa-solid fa-cart-plus" style="font-size:11px"></i> Pesan</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $customDomains->links('pagination.pager') }}</div>

    <p class="text-muted mt-3 mb-0" style="font-size:12px">
      Anda perlu masuk/daftar akun sebelum checkout. Dokumen persyaratan (KTP/NPWP dst.) sama seperti pendaftaran domain .id biasa,
      dan diminta setelah pesanan dibuat, sebelum invoice bisa dibayar.
    </p>
  @endif
</div>
