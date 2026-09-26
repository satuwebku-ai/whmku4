@extends('layouts.admin')

@section('title', 'Harga Reseller/Sub-Reseller')

@section('content')

  @include('admin.domains._nav')

  <div class="mb-4 d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Harga Reseller/Sub-Reseller</h1>
      <p class="small text-muted mb-0" style="max-width:48rem">
        Harga yang DISARANKAN registrar untuk pelanggan/sub-reseller mereka sendiri — beda dari
        <b>TLD Pricing</b> (yang menyimpan harga modal &amp; jual kita). Halaman ini murni
        referensi untuk menyusun paket harga sub-reseller sendiri; tidak ada yang tersimpan
        otomatis ke sistem dari sini.
      </p>
    </div>

    <form method="GET" class="d-flex gap-2">
      <select name="registrar" class="form-select form-select-sm" style="width:14rem" onchange="this.form.submit()">
        <option value="">— Pilih Registrar —</option>
        @foreach ($registrars as $r)
          <option value="{{ $r->id }}" @selected($selected && $selected->id === $r->id)>{{ $r->name }}</option>
        @endforeach
      </select>
    </form>
  </div>

  @if (! $selected)
    <div class="card border rounded-4 p-5 text-center text-muted">
      Pilih registrar dulu di atas untuk melihat harga pelanggan &amp; sub-reseller-nya.
    </div>
  @else

    @foreach ($apiErrors as $error)
      <div class="alert alert-warning py-2 px-3" style="font-size:13px">
        <i class="fa-solid fa-triangle-exclamation"></i> {{ $error }}
      </div>
    @endforeach

    {{-- Harga untuk Pelanggan --}}
    @if ($customerPricing !== null)
      <div class="card border rounded-4 overflow-hidden mb-4">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-1">Harga untuk Pelanggan — {{ $selected->name }}</h2>
          <p class="text-muted mb-0" style="font-size:12px">
            Dari <code>GET /customer-tld-pricings</code>. Ini harga yang {{ $selected->name }} sarankan
            ke pelanggan MEREKA, bukan harga modal yang kita bayar (lihat TLD Pricing untuk itu).
          </p>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="px-4 py-3">Ekstensi</th>
                <th class="text-end py-3">Register/thn</th>
                <th class="text-end py-3">Renewal/thn</th>
                <th class="text-end py-3">Transfer</th>
                <th class="text-center py-3">Premium</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($customerPricing as $row)
                <tr>
                  <td class="px-4 py-2 fw-medium text-dark">{{ $row['extension'] }}</td>
                  <td class="text-end py-2">{{ $row['register'] !== null ? number_format($row['register'], 0, ',', '.') . ' ' . $row['currency'] : '—' }}</td>
                  <td class="text-end py-2">{{ $row['renew'] !== null ? number_format($row['renew'], 0, ',', '.') . ' ' . $row['currency'] : '—' }}</td>
                  <td class="text-end py-2">{{ $row['transfer'] !== null ? number_format($row['transfer'], 0, ',', '.') . ' ' . $row['currency'] : '—' }}</td>
                  <td class="text-center py-2">
                    @if ($row['is_premium'])
                      <span class="badge" style="font-size:9px;background:#fef3c7;color:#92400e">Premium</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif

    {{-- Harga Sub-Reseller, per paket --}}
    @if ($subResellerPricing !== null)
      <div class="mb-2">
        <h2 class="small fw-bold text-dark mb-1">Harga Sub-Reseller — {{ $selected->name }}</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Dari <code>GET /sub-reseller-tld-pricings</code>. Dibagi per paket — tiap paket punya syarat
          deposit minimum &amp; batas saldo sendiri.
        </p>
      </div>

      @forelse ($subResellerPricing as $package)
        <div class="card border rounded-4 overflow-hidden mb-4">
          <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <h3 class="small fw-bold text-dark mb-1">{{ $package['package_name'] }}</h3>
              @if ($package['description'])
                <p class="text-muted mb-0" style="font-size:12px">{{ $package['description'] }}</p>
              @endif
            </div>
            <div class="text-end" style="font-size:12px">
              <div>Deposit minimum: <b>{{ $package['minimum_deposit'] !== null ? 'Rp ' . number_format($package['minimum_deposit'], 0, ',', '.') : '—' }}</b></div>
              <div>Batas saldo: <b>{{ $package['balance_limit'] !== null ? 'Rp ' . number_format($package['balance_limit'], 0, ',', '.') : '—' }}</b></div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                  <th class="px-4 py-3">Ekstensi</th>
                  <th class="text-end py-3">Register/thn</th>
                  <th class="text-end py-3">Renewal/thn</th>
                  <th class="text-end py-3">Transfer</th>
                  <th class="text-center py-3">Premium</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($package['tlds'] as $row)
                  <tr>
                    <td class="px-4 py-2 fw-medium text-dark">{{ $row['extension'] }}</td>
                    <td class="text-end py-2">{{ $row['register'] !== null ? number_format($row['register'], 0, ',', '.') . ' ' . $row['currency'] : '—' }}</td>
                    <td class="text-end py-2">{{ $row['renew'] !== null ? number_format($row['renew'], 0, ',', '.') . ' ' . $row['currency'] : '—' }}</td>
                    <td class="text-end py-2">{{ $row['transfer'] !== null ? number_format($row['transfer'], 0, ',', '.') . ' ' . $row['currency'] : '—' }}</td>
                    <td class="text-center py-2">
                      @if ($row['is_premium'])
                        <span class="badge" style="font-size:9px;background:#fef3c7;color:#92400e">Premium</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      @empty
        <div class="card border rounded-4 p-4 text-center text-muted mb-4">Tidak ada paket sub-reseller.</div>
      @endforelse
    @endif

    @if ($customerPricing === null && $subResellerPricing === null)
      <div class="card border rounded-4 p-5 text-center text-muted">
        Registrar {{ $selected->name }} belum mendukung harga pelanggan/sub-reseller lewat halaman ini.
      </div>
    @endif

  @endif

@endsection
