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

      <div class="mb-3 p-3 rounded-3 border" style="background:#f8fafc">
        <label class="form-label small fw-medium text-dark mb-1">Menu ini punya Subnav</label>
        <p class="text-muted mb-2" style="font-size:12px">
          "{{ $menu->label }}" punya {{ $children->count() }} Subnav
          (dikelola di <a href="{{ route('admin.nav-submenus', ['parent' => $menu->id]) }}">Submenu / Subnav</a>).
          Tentukan yang terjadi saat menu ini diklik di navbar publik.
        </p>

        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100" style="cursor:pointer;{{ $currentLinkMode === 'dropdown' ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
              <input type="radio" name="link_mode" value="dropdown" @checked($currentLinkMode === 'dropdown') class="d-none" data-linkmode-radio>
              Tampilkan dropdown Subnav
            </label>
          </div>
          <div class="col-md-6">
            <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100" style="cursor:pointer;{{ $currentLinkMode === 'child' ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
              <input type="radio" name="link_mode" value="child" @checked($currentLinkMode === 'child') class="d-none" data-linkmode-radio>
              Langsung ke salah satu Subnav
            </label>
          </div>
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
          <p class="text-muted mt-1 mb-0" style="font-size:11px">Subnav lain tetap ada dan bisa dikelola, tapi tidak tampil sebagai dropdown di navbar publik.</p>
        </div>

        <p data-linkmode-field="dropdown" class="text-muted mb-0 {{ $currentLinkMode === 'dropdown' ? '' : 'd-none' }}" style="font-size:11px">
          "Tautan Menuju" di bawah dipakai sebagai tautan induk sebelum daftar Subnav pada dropdown.
        </p>
      </div>

      <script>
      (function () {
        const radios = document.querySelectorAll('[data-linkmode-radio]');
        const fields = document.querySelectorAll('[data-linkmode-field]');
        const destinationSection = document.querySelector('[data-linkmode-section="destination"]');

        function sync() {
          const active = document.querySelector('[data-linkmode-radio]:checked')?.value;
          fields.forEach(el => el.classList.toggle('d-none', el.dataset.linkmodeField !== active));
          radios.forEach(r => {
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
