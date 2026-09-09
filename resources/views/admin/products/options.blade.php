@extends('layouts.admin')

@section('title', 'Opsi Konfigurasi — ' . $product->name)

@section('content')

  <a href="{{ route('admin.products.edit', $product) }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke {{ $product->name }}</a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-1">Opsi Konfigurasi</h1>
  <p class="text-muted mb-4" style="font-size:13px;max-width:48rem">
    Opsi tambahan yang muncul di halaman pemesanan <strong>{{ $product->name }}</strong> dan dipilih klien
    SAAT checkout (mis. "RAM Tambahan +1GB") — beda dari Addon yang dibeli belakangan lewat dashboard klien
    setelah hosting aktif. Opsi yang dipilih otomatis ikut ditagih di setiap invoice perpanjangan.
  </p>

  @if (session('success'))
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:14px;color:#166534;max-width:56rem">
      {{ session('success') }}
    </div>
  @endif
  @if ($errors->any())
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#fef2f2;border:1px solid #fecaca;font-size:14px;color:#b91c1c;max-width:56rem">
      {{ $errors->first() }}
    </div>
  @endif

  <div style="max-width:56rem">
    {{-- ── Tambah Grup Baru ── --}}
    <div class="card border rounded-4 p-4 mb-4">
      <h2 class="small fw-bold text-dark mb-3">Tambah Grup Opsi Baru</h2>
      <form method="POST" action="{{ route('admin.products.options.groups.store', $product) }}" class="row g-3 align-items-end">
        @csrf
        <div class="col-sm-5">
          <label class="form-label small fw-medium text-dark">Nama Grup</label>
          <input type="text" name="name" class="form-control form-control-sm" placeholder="mis. RAM Tambahan" required>
        </div>
        <div class="col-sm-3">
          <label class="form-label small fw-medium text-dark">Cara Pilih</label>
          <select name="selection_type" class="form-select form-select-sm">
            <option value="checkbox">Centang bebas (boleh beberapa)</option>
            <option value="radio">Pilih satu (radio)</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="d-flex align-items-center gap-2 small text-dark mb-2" title="Cuma berlaku untuk grup 'Pilih satu' — klien wajib memilih salah satu opsi">
            <input type="checkbox" name="is_required" value="1" class="form-check-input" style="margin-top:0">
            Wajib pilih
          </label>
        </div>
        <div class="col-sm-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">Tambah</button>
        </div>
      </form>
    </div>

    {{-- ── Daftar Grup ── --}}
    @forelse ($product->optionGroups as $group)
      <div class="card border rounded-4 p-4 mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3 flex-wrap">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h2 class="small fw-bold text-dark mb-0">{{ $group->name }}</h2>
            <span class="badge {{ $group->isRadio() ? 'badge-soft-info' : 'badge-soft-secondary' }}">
              {{ $group->isRadio() ? 'Pilih satu' : 'Centang bebas' }}
            </span>
            @if ($group->isRadio() && $group->is_required)
              <span class="badge badge-soft-warning">Wajib dipilih</span>
            @endif
            @unless ($group->is_active)
              <span class="badge badge-soft-secondary">Nonaktif</span>
            @endunless
          </div>
          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <form method="POST" action="{{ route('admin.products.options.groups.status', [$product, $group]) }}">
              @csrf
              <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $group->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.products.options.groups.destroy', [$product, $group]) }}"
                  data-confirm="Hapus grup &quot;{{ $group->name }}&quot; beserta semua opsinya? Pesanan yang sudah ada tidak terpengaruh."
                  data-confirm-title="Hapus Grup Opsi" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
            </form>
          </div>
        </div>

        <details class="mb-3">
          <summary class="text-accent" style="font-size:12px;cursor:pointer">Edit grup ini</summary>
          <form method="POST" action="{{ route('admin.products.options.groups.update', [$product, $group]) }}" class="row g-3 align-items-end mt-2">
            @csrf @method('PUT')
            <div class="col-sm-5">
              <label class="form-label small fw-medium text-dark">Nama Grup</label>
              <input type="text" name="name" value="{{ $group->name }}" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-3">
              <label class="form-label small fw-medium text-dark">Cara Pilih</label>
              <select name="selection_type" class="form-select form-select-sm">
                <option value="checkbox" @selected($group->selection_type === 'checkbox')>Centang bebas</option>
                <option value="radio" @selected($group->selection_type === 'radio')>Pilih satu (radio)</option>
              </select>
            </div>
            <div class="col-sm-2">
              <label class="d-flex align-items-center gap-2 small text-dark mb-2">
                <input type="checkbox" name="is_required" value="1" @checked($group->is_required) class="form-check-input" style="margin-top:0">
                Wajib pilih
              </label>
            </div>
            <div class="col-sm-2">
              <button type="submit" class="btn btn-primary btn-sm w-100">Simpan</button>
            </div>
          </form>
        </details>

        @if ($group->options->isEmpty())
          <p class="text-muted mb-3" style="font-size:13px">Belum ada opsi di grup ini.</p>
        @else
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr class="text-muted" style="font-size:11px;text-transform:uppercase">
                  <th>Nama Opsi</th>
                  <th>Bulanan</th>
                  <th>3 Bulan</th>
                  <th>6 Bulan</th>
                  <th>Tahunan</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($group->options as $option)
                  <tr style="font-size:13px">
                    <td class="fw-medium text-dark">{{ $option->name }}</td>
                    <td>{{ $option->price_monthly !== null ? 'Rp ' . number_format($option->price_monthly, 0, ',', '.') : '—' }}</td>
                    <td>{{ $option->price_quarterly !== null ? 'Rp ' . number_format($option->price_quarterly, 0, ',', '.') : '—' }}</td>
                    <td>{{ $option->price_semi_annually !== null ? 'Rp ' . number_format($option->price_semi_annually, 0, ',', '.') : '—' }}</td>
                    <td>{{ $option->price_annually !== null ? 'Rp ' . number_format($option->price_annually, 0, ',', '.') : '—' }}</td>
                    <td>
                      <span class="badge {{ $option->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $option->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </td>
                    <td class="text-end">
                      <details>
                        <summary class="text-accent" style="font-size:12px;cursor:pointer;list-style:none">Edit</summary>
                      </details>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="7" class="pt-0" style="border-top:none">
                      <details id="edit-option-{{ $option->id }}">
                        <summary class="d-none"></summary>
                        <form method="POST" action="{{ route('admin.products.options.options.update', [$product, $group, $option]) }}" class="row g-2 align-items-end p-3 rounded-3 mb-2" style="background:#f8fafc">
                          @csrf @method('PUT')
                          <div class="col-sm-3">
                            <label class="text-muted mb-1 d-block" style="font-size:11px">Nama</label>
                            <input type="text" name="name" value="{{ $option->name }}" class="form-control form-control-sm" required>
                          </div>
                          <div class="col-sm-2">
                            <label class="text-muted mb-1 d-block" style="font-size:11px">Bulanan</label>
                            <input type="number" step="1" min="0" name="price_monthly" value="{{ $option->price_monthly }}" class="form-control form-control-sm">
                          </div>
                          <div class="col-sm-2">
                            <label class="text-muted mb-1 d-block" style="font-size:11px">3 Bulan</label>
                            <input type="number" step="1" min="0" name="price_quarterly" value="{{ $option->price_quarterly }}" class="form-control form-control-sm">
                          </div>
                          <div class="col-sm-2">
                            <label class="text-muted mb-1 d-block" style="font-size:11px">6 Bulan</label>
                            <input type="number" step="1" min="0" name="price_semi_annually" value="{{ $option->price_semi_annually }}" class="form-control form-control-sm">
                          </div>
                          <div class="col-sm-2">
                            <label class="text-muted mb-1 d-block" style="font-size:11px">Tahunan</label>
                            <input type="number" step="1" min="0" name="price_annually" value="{{ $option->price_annually }}" class="form-control form-control-sm">
                          </div>
                          <div class="col-sm-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100">OK</button>
                          </div>
                          <div class="col-12 d-flex align-items-center gap-3">
                            <label class="d-flex align-items-center gap-2 small text-dark mb-0">
                              <input type="checkbox" name="is_active" value="1" @checked($option->is_active) class="form-check-input" style="margin-top:0">
                              Aktif
                            </label>
                            <button type="submit" form="delete-option-{{ $option->id }}" class="btn btn-outline-danger btn-sm">Hapus Opsi Ini</button>
                          </div>
                        </form>
                      </details>
                      <form id="delete-option-{{ $option->id }}" method="POST" action="{{ route('admin.products.options.options.destroy', [$product, $group, $option]) }}"
                            data-confirm="Hapus opsi &quot;{{ $option->name }}&quot;?" data-confirm-title="Hapus Opsi" data-confirm-style="danger" data-confirm-label="Ya, Hapus" class="d-none">
                        @csrf @method('DELETE')
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        <details>
          <summary class="text-accent" style="font-size:12px;cursor:pointer">+ Tambah Opsi ke Grup Ini</summary>
          <form method="POST" action="{{ route('admin.products.options.options.store', [$product, $group]) }}" class="row g-2 align-items-end mt-2">
            @csrf
            <div class="col-sm-3">
              <label class="text-muted mb-1 d-block" style="font-size:11px">Nama</label>
              <input type="text" name="name" class="form-control form-control-sm" placeholder="mis. +1GB RAM" required>
            </div>
            <div class="col-sm-2">
              <label class="text-muted mb-1 d-block" style="font-size:11px">Bulanan</label>
              <input type="number" step="1" min="0" name="price_monthly" class="form-control form-control-sm" placeholder="0">
            </div>
            <div class="col-sm-2">
              <label class="text-muted mb-1 d-block" style="font-size:11px">3 Bulan</label>
              <input type="number" step="1" min="0" name="price_quarterly" class="form-control form-control-sm" placeholder="0">
            </div>
            <div class="col-sm-2">
              <label class="text-muted mb-1 d-block" style="font-size:11px">6 Bulan</label>
              <input type="number" step="1" min="0" name="price_semi_annually" class="form-control form-control-sm" placeholder="0">
            </div>
            <div class="col-sm-2">
              <label class="text-muted mb-1 d-block" style="font-size:11px">Tahunan</label>
              <input type="number" step="1" min="0" name="price_annually" class="form-control form-control-sm" placeholder="0">
            </div>
            <div class="col-sm-1">
              <button type="submit" class="btn btn-primary btn-sm w-100">+</button>
            </div>
          </form>
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Kosongkan harga untuk siklus yang tidak ditawarkan opsi ini. Isi 0 kalau opsi ini gratis
            (mis. pilihan "Standar/Tidak perlu" di grup radio).
          </p>
        </details>
      </div>
    @empty
      <div class="card border rounded-4 p-4 text-center text-muted" style="font-size:13px">
        Belum ada grup opsi. Tambahkan lewat form di atas.
      </div>
    @endforelse
  </div>

@endsection
