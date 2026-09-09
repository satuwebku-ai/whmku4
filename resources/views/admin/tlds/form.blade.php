@extends('layouts.admin')

@section('title', $tld->exists ? 'Edit TLD' : 'Tambah TLD')

@section('content')

  <div class="mb-4">
    <a href="{{ route('admin.tlds.index') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke TLD Pricing</a>
    <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $tld->exists ? 'Edit TLD' : 'Tambah TLD' }}</h1>
  </div>

  <form method="POST" action="{{ $tld->exists ? route('admin.tlds.update', $tld) : route('admin.tlds.store') }}" class="card border rounded-4 p-4" style="max-width:48rem">
    @csrf
    @if ($tld->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Ekstensi</label>
        <input type="text" name="extension" value="{{ old('extension', $tld->extension) }}" placeholder=".com" class="form-control form-control-sm" required>
        @error('extension') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Registrar (opsional)</label>
        <select name="registrar_id" class="form-select form-select-sm">
          <option value="">— Tidak ditentukan —</option>
          @foreach ($registrars as $r)
            <option value="{{ $r->id }}" @selected(old('registrar_id', $tld->registrar_id) == $r->id)>{{ $r->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    @if ($tld->exists && $tld->hasCost())
      <div class="rounded-3 px-3 py-2 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:12px;color:#475569">
        <b>Harga modal dari registrar:</b>
        Register Rp {{ number_format($tld->cost_register, 0, ',', '.') }} ·
        Renew Rp {{ number_format($tld->cost_renew, 0, ',', '.') }} ·
        Transfer Rp {{ number_format($tld->cost_transfer, 0, ',', '.') }}
        @if ($tld->cost_synced_at)
          <span class="text-muted">(disinkronkan {{ $tld->cost_synced_at->diffForHumans() }})</span>
        @endif
        <br>Harga modal diperbarui otomatis saat sinkronisasi, jadi tidak bisa diedit manual di sini.
      </div>
    @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Harga Register</label>
        <input type="number" step="0.01" name="register_price" value="{{ old('register_price', $tld->register_price) }}" class="form-control form-control-sm" required>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Harga Renew</label>
        <input type="number" step="0.01" name="renew_price" value="{{ old('renew_price', $tld->renew_price) }}" class="form-control form-control-sm" required>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Harga Transfer</label>
        <input type="number" step="0.01" name="transfer_price" value="{{ old('transfer_price', $tld->transfer_price) }}" class="form-control form-control-sm" required>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Min. Tahun</label>
        <input type="number" name="min_years" value="{{ old('min_years', $tld->min_years ?? 1) }}" class="form-control form-control-sm" required>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Maks. Tahun</label>
        <input type="number" name="max_years" value="{{ old('max_years', $tld->max_years ?? 10) }}" class="form-control form-control-sm" required>
      </div>
    </div>

    {{-- Harga per durasi --}}
    <div class="pt-3 border-top mb-3">
      <h2 class="small fw-bold text-dark mb-1">Harga per Durasi</h2>
      <p class="text-muted mb-3" style="font-size:12px">
        Kosongkan untuk memakai perhitungan otomatis (harga 1 tahun × jumlah tahun).
        Isi hanya kalau ingin memberi diskon untuk pembelian jangka panjang.
      </p>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:13px">
          <thead>
            <tr class="small text-uppercase text-muted" style="background:#f8fafc">
              <th class="py-2">Durasi</th>
              <th class="text-end py-2">Harga Register (Rp)</th>
              <th class="text-end py-2">Harga Renew (Rp)</th>
              <th class="text-end py-2">Otomatis</th>
            </tr>
          </thead>
          <tbody>
            @php
              $maxYears = (int) old('max_years', $tld->max_years ?? 10);
              $yearReg = old('year_prices', $tld->year_prices ?? []);
              $yearRen = old('year_renew_prices', $tld->year_renew_prices ?? []);
            @endphp

            @for ($y = 1; $y <= max($maxYears, 1); $y++)
              <tr>
                <td class="py-2 fw-medium text-dark">{{ $y }} tahun</td>
                <td class="text-end py-2">
                  @if ($y === 1)
                    <span class="text-muted" style="font-size:11px">pakai Harga Register di atas</span>
                  @else
                    <input type="number" step="1" min="0" name="year_prices[{{ $y }}]"
                           value="{{ $yearReg[$y] ?? $yearReg[(string) $y] ?? '' }}"
                           placeholder="{{ number_format((float) old('register_price', $tld->register_price ?? 0) * $y, 0, ',', '') }}"
                           class="form-control form-control-sm text-end" style="width:8rem;display:inline-block">
                  @endif
                </td>
                <td class="text-end py-2">
                  @if ($y === 1)
                    <span class="text-muted" style="font-size:11px">pakai Harga Renew di atas</span>
                  @else
                    <input type="number" step="1" min="0" name="year_renew_prices[{{ $y }}]"
                           value="{{ $yearRen[$y] ?? $yearRen[(string) $y] ?? '' }}"
                           placeholder="{{ number_format((float) old('renew_price', $tld->renew_price ?? 0) * $y, 0, ',', '') }}"
                           class="form-control form-control-sm text-end" style="width:8rem;display:inline-block">
                  @endif
                </td>
                <td class="text-end py-2 text-muted" style="font-size:11px">
                  Rp {{ number_format((float) old('register_price', $tld->register_price ?? 0) * $y, 0, ',', '.') }}
                </td>
              </tr>
            @endfor
          </tbody>
        </table>
      </div>
      <p class="text-muted mt-2 mb-0" style="font-size:11px">
        Angka abu-abu di kolom terakhir adalah harga otomatis. Kolom isian menampilkannya
        sebagai placeholder — kalau dibiarkan kosong, itulah yang dipakai.
      </p>
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tld->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Aktif (tampil di halaman Cek Domain)
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.tlds.index') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>

@endsection
