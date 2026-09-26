@extends('layouts.admin')

@section('title', $menu->exists ? 'Edit Menu Utama' : 'Tambah Menu Utama')

@section('content')
  @include('admin.pages._nav')

  <div class="mb-3">
    <a href="{{ route('admin.nav-menus') }}" class="text-decoration-none text-muted" style="font-size:12px">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Menu Utama
    </a>
  </div>

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $menu->exists ? 'Edit Menu Utama' : 'Tambah Menu Utama' }}</h1>
    <p class="small text-muted mb-0">Menu ini akan tampil langsung di navbar publik. Submenu dibuat di halaman terpisah.</p>
  </div>

  <form method="POST" action="{{ $menu->exists ? route('admin.nav-menu.update', $menu) : route('admin.nav-menu.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Menu Utama</label>
      <input type="text" name="label" value="{{ old('label', $menu->label) }}" class="form-control form-control-sm" placeholder="Contoh: Hosting" maxlength="50" required autofocus>
      @error('label') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    @if ($menu->exists && $children->isNotEmpty())
      @php $currentLinkMode = old('link_mode', $menu->default_child_id ? 'child' : 'dropdown'); @endphp

      {{-- Sengaja dibuat beda gaya (kartu + radio biasa) dari blok
           "Tautan Menuju" di bawah (yang pakai pill) supaya jelas ini
           dua pengaturan yang beda, bukan satu grup pilihan yang sama. --}}
      <div class="mb-3 rounded-4 border overflow-hidden">
        <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2" style="background:#eef2ff">
          <i class="fa-solid fa-diagram-project text-primary" style="font-size:12px"></i>
          <span class="small fw-bold text-dark">Menu ini punya Subnav</span>
        </div>
        <div class="p-3">
          <p class="text-muted mb-3" style="font-size:12px">
            "{{ $menu->label }}" punya {{ $children->count() }} Subnav
            (dikelola di <a href="{{ route('admin.nav-submenus', ['parent' => $menu->id]) }}">Submenu / Subnav</a>).
            Tentukan yang terjadi saat menu ini diklik di navbar publik:
          </p>

          <div class="d-flex flex-column gap-2 mb-3">
            <label class="d-flex align-items-start gap-2 rounded-3 border p-2 mb-0" data-linkmode-option style="cursor:pointer;{{ $currentLinkMode === 'dropdown' ? 'border-color:#4f46e5;background:rgba(79,70,229,.05)' : '' }}">
              <input type="radio" name="link_mode" value="dropdown" @checked($currentLinkMode === 'dropdown') class="form-check-input mt-1 flex-shrink-0" data-linkmode-radio>
              <span>
                <span class="d-block small fw-medium text-dark">Tampilkan dropdown Subnav</span>
                <span class="d-block text-muted" style="font-size:11px">Menu ini jadi tombol dropdown, membuka daftar semua Subnav-nya.</span>
              </span>
            </label>
            <label class="d-flex align-items-start gap-2 rounded-3 border p-2 mb-0" data-linkmode-option style="cursor:pointer;{{ $currentLinkMode === 'child' ? 'border-color:#4f46e5;background:rgba(79,70,229,.05)' : '' }}">
              <input type="radio" name="link_mode" value="child" @checked($currentLinkMode === 'child') class="form-check-input mt-1 flex-shrink-0" data-linkmode-radio>
              <span>
                <span class="d-block small fw-medium text-dark">Langsung ke salah satu Subnav</span>
                <span class="d-block text-muted" style="font-size:11px">Klik menu langsung menuju satu Subnav pilihan, tanpa dropdown. Subnav lain tetap ada, hanya tidak tampil di navbar.</span>
              </span>
            </label>
          </div>

          <div data-linkmode-field="child" class="{{ $currentLinkMode === 'child' ? '' : 'd-none' }}">
            <label class="form-label small fw-medium text-dark">Pilih Subnav Tujuan</label>
            <select name="default_child_id" class="form-select form-select-sm">
              <option value="">— Pilih —</option>
              @foreach ($children as $child)
                <option value="{{ $child->id }}" @selected((int) old('default_child_id', $menu->default_child_id) === $child->id)>{{ $child->label }}</option>
              @endforeach
            </select>
            @error('default_child_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>

          <p data-linkmode-field="dropdown" class="text-muted mb-0 {{ $currentLinkMode === 'dropdown' ? '' : 'd-none' }}" style="font-size:11px">
            <i class="fa-solid fa-circle-info"></i> Bagian "Tautan Menuju" di bawah ini dipakai sebagai tautan induk, muncul di baris paling atas dropdown sebelum daftar Subnav.
          </p>
        </div>
      </div>

      <script>
      (function () {
        const radios = document.querySelectorAll('[data-linkmode-radio]');
        const fields = document.querySelectorAll('[data-linkmode-field]');
        const destinationSection = document.querySelector('[data-linkmode-section="destination"]');

        function sync() {
          const active = document.querySelector('[data-linkmode-radio]:checked')?.value;
          fields.forEach(el => el.classList.toggle('d-none', el.dataset.linkmodeField !== active));
          document.querySelectorAll('[data-linkmode-option]').forEach(label => {
            const isChecked = label.querySelector('[data-linkmode-radio]').checked;
            label.style.borderColor = isChecked ? '#4f46e5' : '';
            label.style.background = isChecked ? 'rgba(79,70,229,.05)' : '';
          });
          if (destinationSection) {
            destinationSection.classList.toggle('d-none', active === 'child');
          }
        }

        radios.forEach(r => r.addEventListener('change', sync));
        sync();
      })();
      </script>
    @endif

    <div data-linkmode-section="destination">
      @if ($menu->exists && $children->isNotEmpty())
        <p class="text-muted mb-2" style="font-size:11px">
          <i class="fa-solid fa-circle-info"></i> Ini tautan milik "{{ $menu->label }}" sendiri (bukan Subnav) — dipakai kalau memilih "Tampilkan dropdown Subnav" di atas.
        </p>
      @endif
      @include('admin.nav-menus._destination-fields')
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Tampilkan di navbar publik
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Simpan Menu Utama</button>
      <a href="{{ route('admin.nav-menus') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>
@endsection
