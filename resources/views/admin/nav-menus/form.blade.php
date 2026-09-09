@extends('layouts.admin')

@section('title', $menu->exists ? 'Edit Menu' : 'Tambah Menu')

@section('content')

  <div class="mb-4">
    <a href="{{ route('admin.nav-menus') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Menu Navigasi</a>
    <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $menu->exists ? 'Edit Menu' : 'Tambah Menu' }}</h1>
  </div>

  <form method="POST" action="{{ $menu->exists ? route('admin.nav-menu.update', $menu) : route('admin.nav-menu.add') }}" class="card border rounded-4 p-4" style="max-width:36rem">
    @csrf

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Menu</label>
      <input type="text" name="label" value="{{ old('label', $menu->label) }}" class="form-control form-control-sm" required placeholder="Tentang Kami">
      @error('label') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Teks yang tampil di navigasi.</p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Menu Induk (opsional)</label>
      <select name="parent_id" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
        <option value="">— Menu utama (tidak jadi submenu) —</option>
        @foreach ($parentOptions as $opt)
          <option value="{{ $opt->id }}" @selected(old('parent_id', $menu->parent_id) == $opt->id)>{{ $opt->label }}</option>
        @endforeach
      </select>
      @error('parent_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Pilih menu induk supaya menu ini tampil sebagai submenu dropdown di bawahnya — kosongkan kalau ini menu utama sendiri.
      </p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Tautan Menuju</label>
      <div class="row g-2">
        @foreach (['route' => 'Halaman Bawaan', 'page' => 'Halaman Saya', 'url' => 'Tautan Bebas'] as $key => $label)
          @php $isActiveType = old('type', $menu->type) === $key; @endphp
          <div class="col-4">
            <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100"
                   style="cursor:pointer;{{ $isActiveType ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
              <input type="radio" name="type" value="{{ $key }}" @checked($isActiveType) class="d-none" data-type-radio>
              {{ $label }}
            </label>
          </div>
        @endforeach
      </div>
      @error('type') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div data-type-field="route" class="mb-3 {{ old('type', $menu->type) === 'route' ? '' : 'd-none' }}">
      <label class="form-label small fw-medium text-dark">Pilih Halaman Bawaan</label>
      <select name="route_name" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
        <option value="">— Pilih —</option>
        @foreach (\App\Models\NavMenu::BUILTIN_ROUTES as $key => $label)
          <option value="{{ $key }}" @selected(old('route_name', $menu->route_name) === $key)>{{ $label }}</option>
        @endforeach
      </select>
      @error('route_name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div data-type-field="page" class="mb-3 {{ old('type', $menu->type) === 'page' ? '' : 'd-none' }}">
      <label class="form-label small fw-medium text-dark">Pilih Halaman</label>
      <select name="page_id" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
        <option value="">— Pilih —</option>
        @forelse ($pages as $page)
          <option value="{{ $page->id }}" @selected((int) old('page_id', $menu->page_id) === $page->id)>{{ $page->title }}</option>
        @empty
          <option value="" disabled>Belum ada halaman yang diterbitkan</option>
        @endforelse
      </select>
      @error('page_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Hanya halaman berstatus Terbit yang muncul di sini.
        <a href="{{ route('admin.page.add.page') }}" class="text-accent">Buat halaman baru →</a>
      </p>
    </div>

    <div data-type-field="url" class="mb-3 {{ old('type', $menu->type) === 'url' ? '' : 'd-none' }}">
      <label class="form-label small fw-medium text-dark">Alamat Tautan</label>
      <input type="text" name="url" value="{{ old('url', $menu->url) }}" class="form-control form-control-sm" placeholder="https://wa.me/6281234567890">
      @error('url') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Bisa ke luar situs, mis. WhatsApp atau media sosial.</p>
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-2">
      <input type="checkbox" name="open_in_new_tab" value="1" @checked(old('open_in_new_tab', $menu->open_in_new_tab)) class="form-check-input" style="margin-top:0">
      Buka di tab baru
    </label>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Tampilkan di navigasi
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.nav-menus') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>

  <script>
    // Tampilkan hanya kolom yang relevan dengan jenis tautan terpilih.
    (function () {
      const radios = document.querySelectorAll('[data-type-radio]');
      const fields = document.querySelectorAll('[data-type-field]');

      function sync() {
        const active = document.querySelector('[data-type-radio]:checked')?.value;

        fields.forEach(function (el) {
          el.classList.toggle('d-none', el.dataset.typeField !== active);
        });

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
