@extends('layouts.admin')
@section('title', 'Pengaturan Halaman Depan')
@section('content')
  @include('admin.settings._nav')

  @php use App\Models\Setting; @endphp

  <div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Pengaturan Halaman Depan</h1>
      <p class="small text-muted mb-0">Atur berapa banyak item dan section mana saja yang tampil di beranda situs publik.</p>
    </div>
    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:11px"></i> Lihat Beranda
    </a>
  </div>

  <form method="POST" action="{{ route('admin.settings.homepage.update') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <h2 class="small fw-bold text-dark mb-1">Susunan Beranda</h2>
    <p class="text-muted mb-3" style="font-size:12px">
      Seret <i class="fa-solid fa-grip-vertical" style="font-size:10px"></i> untuk mengubah urutan,
      matikan sakelar untuk menyembunyikan. Section yang datanya masih kosong otomatis tidak tampil
      walau sakelarnya menyala.
    </p>

    <input type="hidden" name="section_order" id="sectionOrder" value="{{ implode(',', $order) }}">

    <div id="sectionList" class="d-flex flex-column gap-2 mb-4">
      @foreach ($order as $key)
        @php $meta = $sectionMeta[$key]; @endphp
        <div class="d-flex align-items-center gap-2 rounded-3 border px-3 py-2 bg-white" draggable="true" data-key="{{ $key }}">
          <span class="text-muted" style="cursor:grab;font-size:12px"><i class="fa-solid fa-grip-vertical"></i></span>
          <span class="badge badge-soft-secondary" style="font-size:10px;min-width:1.5rem" data-pos>{{ $loop->iteration }}</span>
          <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-medium text-dark" style="font-size:13px">{{ $meta['label'] }}</span>
            <span class="d-block text-muted" style="font-size:11px">
              {{ $meta['desc'] }}
              @if ($meta['empty'])
                <span class="d-block" style="font-size:10px;color:#94a3b8">Tersembunyi otomatis kalau {{ $meta['empty'] }}.</span>
              @endif
            </span>
          </span>
          <div class="form-check form-switch m-0">
            <input type="checkbox" role="switch" class="form-check-input"
                   name="home_show_{{ $key }}" value="1"
                   @checked(Setting::get('home_show_' . $key, '1') === '1')>
          </div>
        </div>
      @endforeach
    </div>

    <h2 class="small fw-bold text-dark mb-3 pt-3 border-top">Jumlah Item per Section</h2>

    <div class="row g-3 mb-4">
      <div class="col-sm-3">
        <label class="form-label small fw-medium text-dark">Paket Hosting</label>
        <input type="number" name="home_featured_limit" min="1" max="12"
               value="{{ old('home_featured_limit', Setting::get('home_featured_limit', 3)) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-3">
        <label class="form-label small fw-medium text-dark">Paket VPS</label>
        <input type="number" name="home_vps_limit" min="1" max="12"
               value="{{ old('home_vps_limit', Setting::get('home_vps_limit', 3)) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-3">
        <label class="form-label small fw-medium text-dark">Kategori Layanan</label>
        <input type="number" name="home_categories_limit" min="0" max="24"
               value="{{ old('home_categories_limit', Setting::get('home_categories_limit', 6)) }}" class="form-control form-control-sm">
        <p class="text-muted mt-1 mb-0" style="font-size:10px">0 = tanpa batas</p>
      </div>
      <div class="col-sm-3">
        <label class="form-label small fw-medium text-dark">Kabar Terbaru</label>
        <input type="number" name="home_announcements_limit" min="1" max="12"
               value="{{ old('home_announcements_limit', Setting::get('home_announcements_limit', 3)) }}" class="form-control form-control-sm">
      </div>
    </div>

    <div class="rounded-3 p-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <h3 class="fw-bold text-dark mb-0" style="font-size:13px">Banner Promo di Beranda</h3>
        <a href="{{ route('admin.promo-banners.index') }}" class="text-accent text-decoration-none" style="font-size:11px">
          Kelola Banner <i class="fa-solid fa-arrow-right" style="font-size:9px"></i>
        </a>
      </div>

      @php $tampil = $banners->where('shows_on_home', true); @endphp

      @if ($banners->isEmpty())
        <p class="text-muted mb-0" style="font-size:11px">
          Belum ada banner sama sekali. Tambahkan lewat <a href="{{ route('admin.promo-banners.create') }}" class="text-accent">Konten &rarr; Banner Promo</a>.
        </p>
      @else
        @if ($tampil->isEmpty())
          <p class="mb-2" style="font-size:11px;color:#b45309">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <b>Tidak ada banner yang tampil di beranda saat ini.</b> Alasannya per banner ada di bawah.
          </p>
        @else
          <p class="mb-2" style="font-size:11px;color:#047857">
            <i class="fa-solid fa-circle-check"></i>
            {{ $tampil->count() }} banner sedang tampil di beranda.
          </p>
        @endif

        <div class="d-flex flex-column gap-1">
          @foreach ($banners as $b)
            <div class="d-flex align-items-start justify-content-between gap-2 rounded-2 px-2 py-1" style="background:#fff;border:1px solid #e2e8f0">
              <div class="min-w-0">
                <span class="fw-medium text-dark" style="font-size:12px">{{ $b['title'] }}</span>
                <span class="text-muted" style="font-size:10px"> &middot; {{ $b['page'] }}</span>
                @if ($b['reasons'])
                  <span class="d-block" style="font-size:10px;color:#b45309">{{ implode(' &middot; ', $b['reasons']) }}</span>
                @endif
              </div>
              <span class="badge flex-shrink-0" style="font-size:9px;background:{{ $b['shows_on_home'] ? '#d1fae5' : '#f1f5f9' }};color:{{ $b['shows_on_home'] ? '#047857' : '#64748b' }}">
                {{ $b['shows_on_home'] ? 'Tampil' : 'Tidak tampil' }}
              </span>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    <button type="submit" class="btn btn-primary btn-sm" style="width:fit-content"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Pengaturan</button>
  </form>

  <script>
    // Drag-and-drop urutan section. Urutan final ditulis ke input
    // tersembunyi #sectionOrder sebagai daftar dipisah koma, jadi ikut
    // terkirim dalam submit form biasa -- tidak butuh AJAX.
    (function () {
      const list = document.getElementById('sectionList');
      const orderInput = document.getElementById('sectionOrder');

      if (! list || ! orderInput) return;

      let dragged = null;

      function sync() {
        const keys = Array.from(list.children).map(function (el, i) {
          const pos = el.querySelector('[data-pos]');
          if (pos) pos.textContent = i + 1;
          return el.dataset.key;
        });
        orderInput.value = keys.join(',');
      }

      list.querySelectorAll('[draggable="true"]').forEach(function (row) {
        row.addEventListener('dragstart', function () {
          dragged = row;
          row.style.opacity = '.4';
        });

        row.addEventListener('dragend', function () {
          row.style.opacity = '';
          dragged = null;
          sync();
        });

        row.addEventListener('dragover', function (e) {
          e.preventDefault();

          if (! dragged || dragged === row) return;

          const box = row.getBoundingClientRect();
          const after = (e.clientY - box.top) > (box.height / 2);

          list.insertBefore(dragged, after ? row.nextSibling : row);
        });
      });

      sync();
    })();
  </script>
@endsection
