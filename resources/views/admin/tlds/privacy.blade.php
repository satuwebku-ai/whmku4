@extends('layouts.admin')

@section('title', 'ID Protection')

@section('content')

  @include('admin.domains._nav')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">ID Protection (WHOIS Privacy)</h1>
    <p class="small text-muted mb-0">
      Tiga tingkat harga, dari yang paling spesifik: <b>per-TLD</b> &rarr; <b>per-registrar</b> &rarr; <b>global (default)</b>.
      Kosongkan kolom harga di tabel manapun untuk jatuh ke tingkat di atasnya.
    </p>
  </div>

  {{-- Harga global/default --}}
  <div class="card border rounded-4 p-4 mb-4" style="max-width:28rem">
    <h2 class="small fw-bold text-dark mb-1">Harga Default (Global)</h2>
    <p class="text-muted mb-3" style="font-size:12px">
      Dipakai kalau registrar dan TLD-nya tidak punya harga sendiri.
      Ditampilkan sebagai opsi tambahan di halaman Keranjang saat klien mendaftarkan domain baru.
    </p>
    <form method="POST" action="{{ route('admin.tlds.addon-pricing') }}">
      @csrf
      <div class="mb-2">
        <label class="form-label small fw-medium text-dark">Harga ID Protection (per tahun)</label>
        <input type="number" name="whois_privacy_price" min="0" step="1000"
               value="{{ \App\Models\Setting::get('whois_privacy_price', 0) }}" class="form-control form-control-sm">
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Kosongkan / isi 0 untuk menjadikannya gratis.</p>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Simpan Harga Default</button>
    </form>
  </div>

  {{-- Harga per registrar --}}
  <div class="card border rounded-4 overflow-hidden mb-4">
    <div class="px-4 py-3 border-bottom">
      <h2 class="small fw-bold text-dark mb-1">Harga per Registrar</h2>
      <p class="text-muted mb-0" style="font-size:12px">
        Kosongkan supaya registrar itu ikut harga default di atas.
      </p>
    </div>

    <form method="POST" action="{{ route('admin.tlds.privacy.registrars') }}">
      @csrf
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr class="small text-uppercase text-muted" style="background:#f8fafc">
              <th class="px-4 py-3">Registrar</th>
              <th class="text-end py-3" style="width:12rem">Harga ID Protection (Rp)</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($registrars as $registrar)
              <tr>
                <td class="px-4 py-2 fw-medium text-dark">
                  {{ $registrar->name }}
                  @if (! $registrar->is_active)
                    <span class="badge badge-soft-secondary ms-1" style="font-size:9px">Nonaktif</span>
                  @endif
                </td>
                <td class="text-end py-2">
                  <input type="number" step="1" min="0"
                         name="registrars[{{ $registrar->id }}][whois_privacy_price]"
                         value="{{ $registrar->whois_privacy_price !== null ? (int) $registrar->whois_privacy_price : '' }}"
                         placeholder="{{ number_format((float) \App\Models\Setting::get('whois_privacy_price', 0), 0, ',', '.') }}"
                         class="form-control form-control-sm text-end" style="width:9rem;margin-left:auto">
                </td>
                <td class="px-4 py-2"></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-4">Belum ada registrar terdaftar.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($registrars->isNotEmpty())
        <div class="px-4 py-3 border-top">
          <button type="submit" class="btn btn-primary btn-sm">Simpan Harga Registrar</button>
        </div>
      @endif
    </form>
  </div>

  {{-- Eligibilitas + harga per TLD --}}
  <div class="card border rounded-4 overflow-hidden">
    <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="small fw-bold text-dark mb-1">Eligibilitas &amp; Harga per TLD</h2>
        <p class="text-muted mb-0" style="font-size:12px">
          TLD di bawah <code>.id</code> dilarang PANDI menawarkan WHOIS Privacy — centang cuma untuk yang benar-benar boleh.
        </p>
      </div>
      <form method="GET" class="d-flex gap-2 flex-wrap">
        <select name="registrar" class="form-select form-select-sm" style="width:11rem">
          <option value="">Semua Registrar</option>
          <option value="none" @selected(request('registrar') === 'none')>— Tidak ditentukan —</option>
          @foreach ($registrars as $r)
            <option value="{{ $r->id }}" @selected((string) request('registrar') === (string) $r->id)>{{ $r->name }}</option>
          @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari ekstensi..." class="form-control form-control-sm" style="width:11rem">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Cari</button>
        @if (request('search') || request('registrar'))
          <a href="{{ route('admin.tlds.privacy') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        @endif
      </form>
    </div>

    <form method="POST" action="{{ route('admin.tlds.privacy.tlds') }}">
      @csrf
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr class="small text-uppercase text-muted" style="background:#f8fafc">
              <th class="px-4 py-3">Ekstensi</th>
              <th class="text-center py-3" style="width:8rem">Boleh Ditawari</th>
              <th class="text-end py-3" style="width:12rem">Harga Khusus (Rp)</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            @php
              $hargaDefault = (float) \App\Models\Setting::get('whois_privacy_price', 0);
            @endphp

            @forelse ($tlds as $tld)
              @php
                // Harga yang BENAR-BENAR berlaku untuk TLD ini, mengikuti
                // urutan yang sama dengan CartService::privacyPriceFor():
                // harga per-TLD -> harga registrar -> harga default.
                $hargaRegistrar = $tld->registrar?->whois_privacy_price;

                if ($tld->whois_privacy_price !== null) {
                    $berlaku = (float) $tld->whois_privacy_price;
                    $sumber = 'khusus TLD ini';
                } elseif ($hargaRegistrar !== null) {
                    $berlaku = (float) $hargaRegistrar;
                    $sumber = 'dari ' . ($tld->registrar->name ?? 'registrar');
                } else {
                    $berlaku = $hargaDefault;
                    $sumber = 'dari harga default';
                }

                $idFamily = $tld->extension === '.id' || str_ends_with($tld->extension, '.id');
              @endphp
              <tr style="{{ $idFamily && $tld->whois_privacy_eligible ? 'background:rgba(239,68,68,.05)' : '' }}">
                <td class="px-4 py-2 fw-medium text-dark">
                  {{ $tld->extension }}
                  @if ($idFamily && $tld->whois_privacy_eligible)
                    <span class="badge" style="font-size:9px;background:#fee2e2;color:#991b1b" title="Domain .id dan turunannya dilarang PANDI menawarkan WHOIS Privacy">
                      langgar PANDI
                    </span>
                  @endif
                  <span class="d-block fw-normal text-muted" style="font-size:10px">{{ $tld->registrar->name ?? 'manual' }}</span>
                </td>
                <td class="text-center py-2">
                  <input type="checkbox" name="eligible[]" value="{{ $tld->id }}" @checked($tld->whois_privacy_eligible) style="margin:0">
                </td>
                <td class="text-end py-2">
                  <input type="number" step="1" min="0"
                         name="rows[{{ $tld->id }}][whois_privacy_price]"
                         value="{{ $tld->whois_privacy_price !== null ? (int) $tld->whois_privacy_price : '' }}"
                         placeholder="{{ $berlaku > 0 ? number_format($berlaku, 0, ',', '.') : 'Gratis' }}"
                         class="form-control form-control-sm text-end" style="width:9rem;margin-left:auto">
                  {{-- Harga yang berlaku SELALU ditampilkan, bukan cuma
                       jadi placeholder -- placeholder hilang begitu kolom
                       diisi, padahal admin tetap perlu tahu acuannya. --}}
                  <span class="d-block text-muted mt-1" style="font-size:10px">
                    Berlaku: <b>{{ $berlaku > 0 ? 'Rp ' . number_format($berlaku, 0, ',', '.') : 'Gratis' }}</b>
                    <span style="opacity:.7">({{ $sumber }})</span>
                  </span>
                </td>
                <td class="px-4 py-2"></td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada TLD yang cocok.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($tlds->isNotEmpty())
        <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
          <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan TLD</button>
          {{ $tlds->links('pagination.bootstrap') }}
        </div>
      @endif
    </form>
  </div>

@endsection
