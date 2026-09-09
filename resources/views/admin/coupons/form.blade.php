@extends('layouts.admin')

@section('title', $coupon->exists ? 'Edit Kupon' : 'Tambah Kupon')

@section('content')

  @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $coupon->exists ? 'Edit Kupon' : 'Tambah Kupon' }}</h1>
  </div>

  <form method="POST" action="{{ $coupon->exists ? route('admin.coupon.update', $coupon) : route('admin.coupon.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Kode Kupon</label>
        <input type="text" name="code" value="{{ old('code', $coupon->code) }}" placeholder="HEMAT30" class="form-control form-control-sm" required style="text-transform:uppercase">
        @error('code') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Otomatis disimpan dalam huruf kapital.</p>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Tipe Diskon</label>
        <select name="type" class="form-select" style="{{ $selectStyle }}">
          <option value="percent" @selected(old('type', $coupon->type ?? 'percent') === 'percent')>Persentase (%)</option>
          <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Nominal Tetap (Rp)</option>
        </select>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nilai Diskon</label>
        <input type="number" step="0.01" min="0.01" name="value" value="{{ old('value', $coupon->value) }}" class="form-control form-control-sm" required>
        @error('value') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Isi angka saja — 30 untuk 30%, atau 50000 untuk Rp 50.000.</p>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Maks. Potongan (Rp, opsional)</label>
        <input type="number" step="1" min="0" name="max_discount" value="{{ old('max_discount', $coupon->max_discount) }}" class="form-control form-control-sm" placeholder="Tanpa batas">
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Berguna untuk tipe persentase, mis. "30% maks Rp 100.000".</p>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Min. Transaksi (Rp)</label>
        <input type="number" step="1" min="0" name="min_order" value="{{ old('min_order', $coupon->min_order ?? 0) }}" class="form-control form-control-sm">
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Dihitung dari subtotal produk yang berlaku saja, bukan seluruh keranjang.</p>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Maks. Pemakaian per Klien</label>
        <input type="number" min="1" name="usage_limit_per_client" value="{{ old('usage_limit_per_client', $coupon->usage_limit_per_client ?? 1) }}" class="form-control form-control-sm" required>
      </div>
    </div>

    <div class="rounded-3 border p-3 mb-3" id="scopeBox">
      <label class="form-label small fw-medium text-dark">Berlaku Untuk</label>
      <div class="row g-2 mb-3">
        @php $appliesAll = old('applies_to', $coupon->applies_to ?? 'all') === 'all'; @endphp
        <div class="col-6">
          <label class="d-flex align-items-center justify-content-center gap-2 rounded-3 border px-3 py-2 text-center small fw-medium"
                 style="cursor:pointer;{{ $appliesAll ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
            <input type="radio" name="applies_to" value="all" @checked($appliesAll) class="d-none" data-scope-radio>
            Semua Produk
          </label>
        </div>
        <div class="col-6">
          <label class="d-flex align-items-center justify-content-center gap-2 rounded-3 border px-3 py-2 text-center small fw-medium"
                 style="cursor:pointer;{{ ! $appliesAll ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
            <input type="radio" name="applies_to" value="specific" @checked(! $appliesAll) class="d-none" data-scope-radio>
            Produk Tertentu
          </label>
        </div>
      </div>

      <div id="scopeSpecific" class="{{ $appliesAll ? 'd-none' : '' }}">
        <div class="mb-3">
          <p class="fw-medium text-dark mb-2" style="font-size:12px">Kategori (semua produk di dalamnya ikut berlaku)</p>
          <div class="d-flex flex-wrap gap-2">
            @forelse ($categories as $cat)
              @php $checkedCats = old('category_ids', $coupon->exists ? $coupon->categories->pluck('id')->all() : []); @endphp
              <label class="d-flex align-items-center gap-2 border rounded-pill px-3 py-2" style="font-size:12px;cursor:pointer">
                <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $checkedCats)) class="form-check-input" style="margin-top:0">
                {{ $cat->name }}
              </label>
            @empty
              <p class="text-muted mb-0" style="font-size:12px">Belum ada kategori produk.</p>
            @endforelse
          </div>
        </div>

        <div>
          <p class="fw-medium text-dark mb-2" style="font-size:12px">Atau produk spesifik (di luar kategori manapun di atas)</p>
          <div class="d-flex flex-wrap gap-2" style="max-height:10rem;overflow-y:auto">
            @forelse ($products as $p)
              @php $checkedProducts = old('product_ids', $coupon->exists ? $coupon->products->pluck('id')->all() : []); @endphp
              <label class="d-flex align-items-center gap-2 border rounded-pill px-3 py-2" style="font-size:12px;cursor:pointer">
                <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, $checkedProducts)) class="form-check-input" style="margin-top:0">
                {{ $p->name }}
              </label>
            @empty
              <p class="text-muted mb-0" style="font-size:12px">Belum ada produk.</p>
            @endforelse
          </div>
        </div>
        @error('scope') <p class="text-danger mt-2 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Kuota Total (opsional)</label>
        <input type="number" min="1" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" class="form-control form-control-sm" placeholder="Tanpa batas">
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Mulai Berlaku</label>
        <input type="date" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d')) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Berlaku Sampai</label>
        <input type="date" name="expires_at" value="{{ old('expires_at', optional($coupon->expires_at)->format('Y-m-d')) }}" class="form-control form-control-sm">
        @error('expires_at') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Aktif
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.coupons') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>

  <script>
    (function () {
      const radios = document.querySelectorAll('[data-scope-radio]');
      const box = document.getElementById('scopeSpecific');

      function sync() {
        const active = document.querySelector('[data-scope-radio]:checked')?.value;
        box.classList.toggle('d-none', active !== 'specific');

        radios.forEach(function (r) {
          const label = r.closest('label');
          if (r.checked) {
            label.style.borderColor = '#4f46e5';
            label.style.background = 'rgba(79,70,229,.06)';
            label.style.color = '#4338ca';
          } else {
            label.style.borderColor = '';
            label.style.background = '';
            label.style.color = '';
          }
        });
      }

      radios.forEach(r => r.addEventListener('change', sync));
      sync();
    })();
  </script>

@endsection
