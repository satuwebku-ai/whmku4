<div class="mb-3">
  <label class="form-label small fw-medium text-dark">Tautan Menuju</label>
  <div class="row g-2">
    @foreach (['route' => 'Halaman Bawaan', 'page' => 'Halaman Saya', 'url' => 'Tautan Bebas'] as $key => $label)
      @php $isActiveType = old('type', $menu->type) === $key; @endphp
      <div class="col-md-4">
        <label class="d-flex align-items-center justify-content-center rounded-3 border px-2 py-2 text-center small fw-medium w-100" style="cursor:pointer;{{ $isActiveType ? 'border-color:#4f46e5!important;background:rgba(79,70,229,.06);color:#4338ca' : '' }}">
          <input type="radio" name="type" value="{{ $key }}" @checked($isActiveType) class="d-none" data-type-radio>
          {{ $label }}
        </label>
      </div>
    @endforeach
  </div>
</div>

<div data-type-field="route" class="mb-3 {{ old('type', $menu->type) === 'route' ? '' : 'd-none' }}">
  <label class="form-label small fw-medium text-dark">Pilih Halaman Bawaan</label>
  <select name="route_name" class="form-select form-select-sm">
    <option value="">— Pilih —</option>
    @foreach (\App\Models\NavMenu::BUILTIN_ROUTES as $key => $label)
      <option value="{{ $key }}" @selected(old('route_name', $menu->route_name) === $key)>{{ $label }}</option>
    @endforeach
  </select>
  @error('route_name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
</div>

<div data-type-field="page" class="mb-3 {{ old('type', $menu->type) === 'page' ? '' : 'd-none' }}">
  <label class="form-label small fw-medium text-dark">Pilih Halaman CMS</label>
  <select name="page_id" class="form-select form-select-sm">
    <option value="">— Pilih —</option>
    @forelse ($pages as $page)
      <option value="{{ $page->id }}" @selected((int) old('page_id', $menu->page_id) === $page->id)>{{ $page->title }}</option>
    @empty
      <option value="" disabled>Belum ada halaman yang diterbitkan</option>
    @endforelse
  </select>
  @error('page_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
  <p class="text-muted mt-1 mb-0" style="font-size:11px">Hanya halaman yang sudah terbit yang tersedia.</p>
</div>

<div data-type-field="url" class="mb-3 {{ old('type', $menu->type) === 'url' ? '' : 'd-none' }}">
  <label class="form-label small fw-medium text-dark">Alamat Tautan</label>
  <input type="text" name="url" value="{{ old('url', $menu->url) }}" class="form-control form-control-sm" placeholder="https://wa.me/6281234567890">
  @error('url') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
</div>

<label class="d-flex align-items-center gap-2 small text-dark mb-3">
  <input type="checkbox" name="open_in_new_tab" value="1" @checked(old('open_in_new_tab', $menu->open_in_new_tab)) class="form-check-input" style="margin-top:0">
  Buka di tab baru
</label>

<script>
(function () {
  const radios = document.querySelectorAll('[data-type-radio]');
  const fields = document.querySelectorAll('[data-type-field]');

  function sync() {
    const active = document.querySelector('[data-type-radio]:checked')?.value;
    fields.forEach(el => el.classList.toggle('d-none', el.dataset.typeField !== active));
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
  }

  radios.forEach(r => r.addEventListener('change', sync));
  sync();
})();
</script>
