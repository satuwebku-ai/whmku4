@extends('layouts.admin')
@section('title', 'Produk')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Produk</h1>
      <p class="small text-muted mb-0">Katalog paket hosting/layanan yang dijual di halaman publik.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('admin.product-categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-folder" style="font-size:11px"></i> Kategori</a>
      <a href="{{ route('admin.addons.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-puzzle-piece" style="font-size:11px"></i> Addons</a>
      <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Produk</a>
    </div>
  </div>

  <style>
    .grp-head { cursor:pointer; background:#f8fafc; }
    .grp-head .grp-chevron { transition:transform .15s; font-size:11px; }
    .grp-head[aria-expanded="false"] .grp-chevron { transform:rotate(-90deg); }
  </style>

  <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama produk..." class="form-control form-control-sm" style="max-width:16rem;flex:1 1 180px">
    <select name="category_id" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:14rem" data-auto-submit>
      <option value="">Semua Grup</option>
      @foreach ($categories as $cat)
        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
    @if ($search !== '' || request('category_id'))
      <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
    @endif
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="small text-muted">{{ $groups->count() }} grup · {{ $totalProducts }} produk</span>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-grp-all="open">Buka semua</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-grp-all="close">Tutup semua</button>
    </div>
  </form>

  @forelse ($groups as $group)
    @php $type = $group->productType; @endphp
    <div class="card border rounded-4 overflow-hidden mb-3">
      <div class="grp-head d-flex align-items-center gap-3 px-4 py-3" data-grp-toggle
           data-bs-toggle="collapse" data-bs-target="#grp{{ $group->id }}" role="button"
           tabindex="0" aria-expanded="true" aria-controls="grp{{ $group->id }}">
        <i class="fa-solid fa-chevron-down grp-chevron text-muted"></i>
        <div class="flex-grow-1">
          <p class="fw-semibold text-dark mb-0">
            @if ($group->icon)<i class="fa-solid {{ $group->icon }} text-muted me-1"></i>@endif
            {{ $group->name }}
            @if ($type)
              <span class="badge ms-1" style="font-size:9px;background:{{ $type->color }}1a;color:{{ $type->color }}">
                @if ($type->icon)<i class="fa-solid {{ $type->icon }}"></i>@endif {{ $type->name }}
              </span>
            @endif
            @unless ($group->is_active)
              <span class="badge badge-soft-secondary ms-1" style="font-size:9px">Nonaktif</span>
            @endunless
          </p>
          @if ($type)
            <p class="text-muted mb-0" style="font-size:11px">/{{ $type->slug }}/{{ $group->slug }}</p>
          @endif
        </div>
        <span class="badge badge-soft-secondary">{{ $group->products->count() }} produk</span>
        <a href="{{ route('admin.product-categories.edit', $group) }}" class="btn btn-outline-secondary btn-sm" title="Edit grup">
          <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
        </a>
      </div>

      <div id="grp{{ $group->id }}" class="collapse show">
        <div class="table-responsive border-top">
          {{-- Lebar kolom tetap supaya semua grup sejajar --}}
          <table class="table table-hover align-middle mb-0" style="table-layout:fixed;min-width:760px">
            <colgroup>
              <col>
              <col style="width:150px">
              <col style="width:110px">
              <col style="width:100px">
              <col style="width:210px">
            </colgroup>
            <thead>
              <tr class="small text-uppercase text-muted">
                <th class="text-start px-4 py-3">Produk</th>
                <th class="text-end py-3">Mulai Dari</th>
                <th class="text-start py-3">Domain</th>
                <th class="text-start py-3">Status</th>
                <th class="text-end px-4 py-3">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($group->products as $product)
                <tr>
                  <td class="px-4 py-3">
                    @php
                      $isVps = $product->server_id && $cloudServerIds->contains($product->server_id);
                      $spec = $isVps ? json_decode((string) $product->panel_package, true) : null;
                    @endphp
                    <p class="fw-medium text-dark mb-0">
                      {{ $product->name }}
                      @if ($isVps)
                        <span class="badge badge-soft-success ms-1" style="font-size:9px"><i class="fa-solid fa-cloud"></i> VPS</span>
                      @endif
                      @if ($product->is_featured)
                        <i class="fa-solid fa-star text-warning ms-1" style="font-size:10px" title="Unggulan"></i>
                      @endif
                    </p>
                    @if ($isVps && is_array($spec) && isset($spec['vcpu']))
                      <p class="text-muted mb-0" style="font-size:11px">
                        {{ $spec['vcpu'] }} vCPU · {{ $spec['ram'] }} MB · {{ $spec['disk'] }} GB · {{ $spec['os_name'] ?? '' }}
                      </p>
                    @elseif ($isVps)
                      <p class="mb-0" style="font-size:11px;color:#b45309">
                        <i class="fa-solid fa-triangle-exclamation"></i> Spesifikasi VPS belum diisi
                      </p>
                    @elseif ($product->tagline)
                      <p class="text-muted mb-0" style="font-size:12px">{{ $product->tagline }}</p>
                    @endif
                  </td>
                  <td class="text-end text-dark py-3">
                    @if ($product->starting_price !== null)
                      Rp {{ number_format($product->starting_price, 0, ',', '.') }}
                    @else
                      <span class="text-danger" style="font-size:12px">Belum ada harga</span>
                    @endif
                  </td>
                  <td class="text-muted text-capitalize py-3" style="font-size:12px">{{ $product->domain_option }}</td>
                  <td class="py-3">
                    <span class="badge {{ $product->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                  </td>
                  <td class="text-end px-4 py-3">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                      <form method="POST" action="{{ route('admin.product.status') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                          <i class="fa-solid {{ $product->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}" style="font-size:12px"></i>
                        </button>
                      </form>
                      @if ($product->is_active && $type)
                        <a href="{{ $group->productUrl($product) }}" target="_blank" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Lihat di katalog">
                          <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:12px"></i>
                        </a>
                      @endif
                      <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                        <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                      </a>
                      <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                            data-confirm="Hapus produk {{ $product->name }}?" data-confirm-title="Hapus Produk"
                            data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                          <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4 small">Belum ada produk di grup ini.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @empty
    <div class="card border rounded-4">
      <div class="text-center text-muted py-5">
        @if ($search !== '')
          Tidak ada produk yang cocok dengan "{{ $search }}".
        @else
          Belum ada grup produk. <a href="{{ route('admin.product-categories.create') }}">Buat grup</a> dulu.
        @endif
      </div>
    </div>
  @endforelse

  <script @nonce>
    document.querySelectorAll('[data-grp-all]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var open = btn.dataset.grpAll === 'open';
        document.querySelectorAll('[data-grp-toggle]').forEach(function (head) {
          if ((head.getAttribute('aria-expanded') === 'true') !== open) head.click();
        });
      });
    });
  </script>
@endsection
