@extends('layouts.admin')

@section('title', 'Domain Premium')

@section('content')

  @include('admin.domains._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Domain Premium</h1>
      <p class="small text-muted mb-0" style="max-width:48rem">
        Harga MODAL keluarga .id ditarik otomatis dari DNAMA (tingkat harganya ditetapkan PANDI
        berdasarkan jumlah karakter) — harga JUAL diisi manual di sini dan tersimpan permanen,
        tidak pernah ditimpa oleh sinkronisasi ulang.
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
      Pilih registrar dulu di atas untuk mengelola harga domain premium-nya.
    </div>
  @else

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="small fw-bold text-dark mb-0">Keluarga .id — {{ $selected->name }}</h2>
      <form method="POST" action="{{ route('admin.tld.premium-pricing.sync') }}">
        @csrf
        <input type="hidden" name="registrar_id" value="{{ $selected->id }}">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-cloud-arrow-down" style="font-size:11px"></i> Sinkron Harga Modal dari {{ $selected->name }}
        </button>
      </form>
    </div>

    @if ($familyRows->isEmpty())
      <div class="card border rounded-4 p-5 text-center text-muted mb-4">
        Belum ada data. Tekan "Sinkron Harga Modal" di atas untuk menariknya dari {{ $selected->name }}.
      </div>
    @else
      <form method="POST" action="{{ route('admin.tld.premium-pricing.update') }}">
        @csrf
        <input type="hidden" name="registrar_id" value="{{ $selected->id }}">

        <div class="card border rounded-4 overflow-hidden mb-4">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                  <th class="px-4 py-3">Ekstensi</th>
                  <th class="text-end py-3">Modal Register</th>
                  <th class="text-end py-3">Modal Renew</th>
                  <th class="text-end py-3">Modal Transfer</th>
                  <th class="text-end py-3" style="width:9rem">Jual Register</th>
                  <th class="text-end py-3" style="width:9rem">Jual Renew</th>
                  <th class="text-end py-3" style="width:9rem">Jual Transfer</th>
                  <th class="py-3">Disinkron</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($familyRows as $row)
                  <tr>
                    <td class="px-4 py-2 fw-medium text-dark">
                      {{ $row->label }}
                      @if ($row->is_premium)
                        <span class="badge ms-1" style="font-size:9px;background:#fef3c7;color:#92400e">Premium</span>
                      @endif
                    </td>
                    <td class="text-end py-2 text-muted">{{ $row->cost_register !== null ? number_format($row->cost_register, 0, ',', '.') . ' ' . $row->cost_currency : '—' }}</td>
                    <td class="text-end py-2 text-muted">{{ $row->cost_renew !== null ? number_format($row->cost_renew, 0, ',', '.') . ' ' . $row->cost_currency : '—' }}</td>
                    <td class="text-end py-2 text-muted">{{ $row->cost_transfer !== null ? number_format($row->cost_transfer, 0, ',', '.') . ' ' . $row->cost_currency : '—' }}</td>
                    <td class="py-2">
                      <input type="number" step="1" min="0" name="rows[{{ $row->id }}][sell_register_price]" value="{{ $row->sell_register_price }}" class="form-control form-control-sm text-end" placeholder="belum diisi">
                    </td>
                    <td class="py-2">
                      <input type="number" step="1" min="0" name="rows[{{ $row->id }}][sell_renew_price]" value="{{ $row->sell_renew_price }}" class="form-control form-control-sm text-end" placeholder="belum diisi">
                    </td>
                    <td class="py-2">
                      <input type="number" step="1" min="0" name="rows[{{ $row->id }}][sell_transfer_price]" value="{{ $row->sell_transfer_price }}" class="form-control form-control-sm text-end" placeholder="belum diisi">
                    </td>
                    <td class="py-2 text-muted" style="font-size:11px">
                      {{ $row->cost_synced_at?->diffForHumans() ?? '—' }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Harga Jual
        </button>
      </form>
    @endif

    @if ($genericRows->isNotEmpty())
      <div class="mt-5">
        <h2 class="small fw-bold text-dark mb-1">Ekstensi Generik — cek &amp; pesan lewat tiket</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Harga domain premium generik ditentukan PER-NAMA oleh {{ $selected->name }} (tidak ada
          daftar tetap seperti keluarga .id), dan pemesanannya diproses manual, bukan lewat API
          registrasi biasa. Daftar di bawah cuma referensi ekstensi apa saja yang bisa dicek.
        </p>
        <div class="d-flex flex-wrap gap-2">
          @foreach ($genericRows as $row)
            <span class="badge badge-soft-secondary" style="font-size:12px">{{ $row->extension }}</span>
          @endforeach
        </div>
      </div>
    @endif

  @endif

@endsection
