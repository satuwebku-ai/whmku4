@extends('layouts.admin')

@section('title', 'Domain Premium Custom')

@section('content')

  @include('admin.domains._nav')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Domain Premium Custom</h1>
    <p class="small text-muted mb-0" style="max-width:48rem">
      Daftar nama domain premium tertentu (mis. <code>abner.id</code>) dengan harga modal masing-masing.
      Impor dari Excel/CSV, lalu isi harga jual. Domain yang harga jualnya kosong <strong>tidak dijual</strong> ke publik,
      dan harga jual tidak boleh di bawah modal. Domain yang sudah dipesan otomatis hilang dari halaman publik.
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger small py-2">{{ $errors->first() }}</div>
  @endif

  <div class="row g-3 mb-4">
    @foreach ([['Total domain', $totals['all']], ['Dijual (tampil di publik)', $totals['for_sale']], ['Belum ada harga jual', $totals['no_price']]] as [$label, $value])
      <div class="col-6 col-md-4">
        <div class="card border rounded-4 p-3">
          <div class="text-muted" style="font-size:11px">{{ strtoupper($label) }}</div>
          <div class="fw-bold text-dark" style="font-size:20px">{{ number_format($value, 0, ',', '.') }}</div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <form method="POST" action="{{ route('admin.tld.premium-custom.import') }}" enctype="multipart/form-data" class="card border rounded-4 p-4 h-100">
        @csrf
        <h2 class="h6 fw-bold text-dark mb-1">Impor dari Excel / CSV</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Kolom: <strong>#, Domain Premium, Karakter, Usia, Harga modal</strong>. Teks "Premium" di belakang nama dan format
          "Rp 6.700.000,00" dikenali otomatis. Domain yang sudah ada diperbarui modalnya; harga jual yang sudah Anda isi tidak ditimpa.
          <a href="{{ route('admin.tld.premium-custom.template') }}">Unduh template (CSV)</a>
        </p>
        <input type="file" name="file" accept=".xlsx,.csv,.txt" class="form-control form-control-sm mb-2" required>
        <label class="form-label small mb-1">Margin otomatis untuk domain baru (%, opsional)</label>
        <input type="number" name="margin_percent" min="0" max="1000" step="0.1" value="{{ old('margin_percent') }}" placeholder="kosongkan = isi harga jual manual" class="form-control form-control-sm mb-3">
        <button type="submit" class="btn btn-primary btn-sm align-self-start"><i class="fa-solid fa-file-import" style="font-size:11px"></i> Impor</button>
      </form>
    </div>

    <div class="col-lg-6">
      <form method="POST" action="{{ route('admin.tld.premium-custom.store') }}" class="card border rounded-4 p-4 h-100">
        @csrf
        <h2 class="h6 fw-bold text-dark mb-3">Tambah manual</h2>
        <div class="row g-2">
          <div class="col-sm-7">
            <label class="form-label small mb-1">Domain</label>
            <input type="text" name="domain_name" value="{{ old('domain_name') }}" placeholder="abner.id" class="form-control form-control-sm" required>
          </div>
          <div class="col-sm-5">
            <label class="form-label small mb-1">Usia</label>
            <input type="text" name="age_label" value="{{ old('age_label', '1 tahun') }}" class="form-control form-control-sm">
          </div>
          <div class="col-sm-4">
            <label class="form-label small mb-1">Harga modal (Rp)</label>
            <input type="number" name="cost_price" min="0" step="1" value="{{ old('cost_price') }}" class="form-control form-control-sm" required>
          </div>
          <div class="col-sm-4">
            <label class="form-label small mb-1">Harga jual (Rp)</label>
            <input type="number" name="sell_price" min="0" step="1" value="{{ old('sell_price') }}" class="form-control form-control-sm">
          </div>
          <div class="col-sm-4">
            <label class="form-label small mb-1">Perpanjangan (Rp)</label>
            <input type="number" name="renew_price" min="0" step="1" value="{{ old('renew_price') }}" class="form-control form-control-sm">
          </div>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm align-self-start mt-3"><i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah</button>
      </form>
    </div>
  </div>

  <form method="GET" class="d-flex gap-2 mb-3" style="max-width:24rem">
    <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama domain…" class="form-control form-control-sm">
    <button class="btn btn-outline-secondary btn-sm">Cari</button>
  </form>

  @if ($domains->isEmpty())
    <div class="card border rounded-4 p-5 text-center text-muted">
      {{ $q !== '' ? 'Tidak ada domain yang cocok.' : 'Belum ada domain custom. Impor file atau tambah manual di atas.' }}
    </div>
  @else
    <form method="POST" action="{{ route('admin.tld.premium-custom.update') }}">
      @csrf
      <div class="card border rounded-4 overflow-hidden mb-3">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="ps-4">Domain</th>
                <th class="text-center">Kar.</th>
                <th>Usia</th>
                <th class="text-end">Modal</th>
                <th style="width:9rem">Jual</th>
                <th style="width:9rem">Perpanjangan</th>
                <th class="text-end">Margin</th>
                <th class="text-center">Aktif</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($domains as $d)
                @php
                  $isTaken = in_array(strtolower($d->domain_name), $taken, true);
                  $sell = old("rows.{$d->id}.sell_price", $d->sell_price !== null ? (float) $d->sell_price : null);
                  $margin = ($d->sell_price !== null && (float) $d->sell_price > 0) ? (float) $d->sell_price - (float) $d->cost_price : null;
                @endphp
                <tr>
                  <td class="ps-4 fw-semibold text-dark">{{ $d->domain_name }}</td>
                  <td class="text-center text-muted">{{ $d->characters }}</td>
                  <td class="text-muted">{{ $d->age_label ?: '—' }}</td>
                  <td class="text-end text-muted">Rp {{ number_format((float) $d->cost_price, 0, ',', '.') }}</td>
                  <td><input type="number" min="0" step="1" name="rows[{{ $d->id }}][sell_price]" value="{{ $sell }}" class="form-control form-control-sm" placeholder="kosong = tidak dijual"></td>
                  <td><input type="number" min="0" step="1" name="rows[{{ $d->id }}][renew_price]" value="{{ old("rows.{$d->id}.renew_price", $d->renew_price !== null ? (float) $d->renew_price : null) }}" class="form-control form-control-sm"></td>
                  <td class="text-end small {{ $margin !== null && $margin < 0 ? 'text-danger' : 'text-success' }}">{{ $margin !== null ? 'Rp ' . number_format($margin, 0, ',', '.') : '—' }}</td>
                  <td class="text-center">
                    <input type="hidden" name="rows[{{ $d->id }}][is_active]" value="0">
                    <input type="checkbox" class="form-check-input" name="rows[{{ $d->id }}][is_active]" value="1" @checked($d->is_active)>
                  </td>
                  <td class="small">
                    @if ($isTaken)
                      <span class="badge text-bg-secondary">Sudah dipesan</span>
                    @elseif ($d->is_active && $d->sell_price !== null && (float) $d->sell_price > 0)
                      <span class="badge text-bg-success">Dijual</span>
                    @else
                      <span class="badge text-bg-warning">Belum dijual</span>
                    @endif
                  </td>
                  <td class="pe-3 text-end">
                    <button type="submit" form="del-{{ $d->id }}" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash" style="font-size:11px"></i></button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-5">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Perubahan</button>
        <div class="small text-muted">
          Halaman {{ $domains->currentPage() }} dari {{ $domains->lastPage() }} ·
          @if ($domains->previousPageUrl())<a href="{{ $domains->previousPageUrl() }}">‹ Sebelumnya</a>@endif
          @if ($domains->nextPageUrl())<a href="{{ $domains->nextPageUrl() }}" class="ms-2">Berikutnya ›</a>@endif
        </div>
      </div>
    </form>

    @foreach ($domains as $d)
      <form id="del-{{ $d->id }}" method="POST" action="{{ route('admin.tld.premium-custom.destroy', $d) }}" class="d-none" data-confirm="Hapus {{ $d->domain_name }} dari daftar?" data-confirm-title="Hapus" data-confirm-style="warn" data-confirm-label="Ya, Hapus">
        @csrf
        @method('DELETE')
      </form>
    @endforeach
  @endif

@endsection
