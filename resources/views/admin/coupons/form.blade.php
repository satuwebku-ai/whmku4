@extends('layouts.admin')

@section('title', $coupon->exists ? 'Edit Kupon' : 'Tambah Kupon')

@section('content')

  @php
    $selectStyle  = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem';
    $appliesAll   = old('applies_to', $coupon->applies_to ?? 'all') === 'all';
    $checkedCats  = array_map('intval', (array) old('category_ids', $coupon->exists ? $coupon->categories->pluck('id')->all() : []));
    $checkedProds = array_map('intval', (array) old('product_ids', $coupon->exists ? $coupon->products->pluck('id')->all() : []));
    $checkedTlds  = array_values(array_map('intval', (array) old('tld_ids', $coupon->tld_ids ?? [])));
    $isPublic     = (bool) old('is_public', $coupon->is_public ?? false);

    // Data TLD untuk modal pemilih (dirender sebagai JSON, bukan ratusan elemen).
    $tldData = $tlds->map(fn ($t) => [
        'id'    => $t->id,
        'ext'   => $t->extension,
        'group' => $t->search_group ?: 'Lainnya',
        'reg'   => $t->registrar->name ?? 'manual',
        'price' => (int) $t->register_price,
        'web'   => (bool) $t->show_in_search,
    ])->values();
  @endphp

  <style>
    .cf-wrap{max-width:60rem}
    .cf-card{background:#fff;border:1px solid #e5e7eb;border-radius:1rem;padding:1.25rem 1.5rem;margin-bottom:1rem}
    .cf-card h2{font-size:.95rem;font-weight:700;margin:0 0 .15rem;color:#111827}
    .cf-card .cf-sub{font-size:12px;color:#6b7280;margin:0 0 1rem}
    .cf-hint{font-size:11px;color:#6b7280;margin:.3rem 0 0}
    .cf-err{font-size:12px;color:#dc2626;margin:.3rem 0 0}
    .cf-seg{display:flex;gap:.5rem}
    .cf-seg label{flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.55rem .75rem;border:1px solid #e5e7eb;border-radius:.6rem;font-size:13px;font-weight:600;cursor:pointer;color:#374151;transition:.15s}
    .cf-seg label:hover{background:#f9fafb}
    .cf-seg label.on{border-color:#4f46e5;background:rgba(79,70,229,.07);color:#4338ca}
    .cf-seg input{display:none}
    .cf-pill{display:inline-flex;align-items:center;gap:.4rem;border:1px solid #e5e7eb;border-radius:999px;padding:.3rem .75rem;font-size:12px;cursor:pointer;background:#fff;transition:.15s}
    .cf-pill:hover{background:#f9fafb}
    .cf-pill.on{border-color:#4f46e5;background:rgba(79,70,229,.07);color:#4338ca}
    .cf-pill input{margin:0}
    .cf-chip{display:inline-flex;align-items:center;gap:.35rem;background:#eef2ff;color:#4338ca;border-radius:999px;padding:.2rem .3rem .2rem .7rem;font-size:12px;font-weight:600}
    .cf-chip button{border:0;background:rgba(79,70,229,.15);color:#4338ca;border-radius:50%;width:18px;height:18px;line-height:16px;padding:0;font-size:12px;cursor:pointer}
    .cf-sub-title{font-size:12px;font-weight:600;color:#111827;margin:0 0 .5rem}
    .cf-scroll{max-height:9rem;overflow-y:auto}
    .cf-actions{position:sticky;bottom:0;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);border:1px solid #e5e7eb;border-radius:1rem;padding:.75rem 1rem;display:flex;gap:.5rem;align-items:center;justify-content:flex-end}
    .cf-switch{display:flex;align-items:flex-start;gap:.6rem;cursor:pointer}
    .cf-switch input{margin-top:.2rem}

    /* modal pemilih TLD */
    .tm-back{position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:2000;display:none;align-items:center;justify-content:center;padding:1rem}
    .tm-back.open{display:flex}
    .tm{background:#fff;border-radius:1rem;width:100%;max-width:52rem;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.3)}
    .tm-head{padding:1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between}
    .tm-head h3{font-size:1rem;font-weight:700;margin:0}
    .tm-x{border:0;background:none;font-size:22px;line-height:1;color:#6b7280;cursor:pointer}
    .tm-tools{padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
    .tm-tools input[type=search]{flex:1 1 12rem}
    .tm-body{padding:1rem 1.25rem;overflow-y:auto;flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(10.5rem,1fr));gap:.5rem;align-content:start}
    .tm-item{display:flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:.6rem;padding:.45rem .65rem;cursor:pointer;font-size:12.5px;transition:.12s}
    .tm-item:hover{background:#f9fafb}
    .tm-item.on{border-color:#4f46e5;background:rgba(79,70,229,.07)}
    .tm-item input{margin:0;flex-shrink:0}
    .tm-item b{display:block;font-weight:600;color:#111827}
    .tm-item small{display:block;color:#6b7280;font-size:10.5px}
    .tm-empty{grid-column:1/-1;text-align:center;color:#6b7280;font-size:13px;padding:2rem 0}
    .tm-foot{padding:.85rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}
  </style>

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $coupon->exists ? 'Edit Kupon' : 'Tambah Kupon' }}</h1>
    <p class="text-muted mb-0" style="font-size:13px">Atur diskon, sasaran, masa berlaku, dan apakah kupon tampil di halaman Promo publik.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger cf-wrap" style="font-size:13px">
      <strong>Periksa kembali isian Anda:</strong>
      <ul class="mb-0 mt-1 ps-3">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" id="couponForm" action="{{ $coupon->exists ? route('admin.coupon.update', $coupon) : route('admin.coupon.add') }}" class="cf-wrap">
    @csrf

    {{-- 1. Kode & diskon --}}
    <div class="cf-card">
      <h2>Kode &amp; Diskon</h2>
      <p class="cf-sub">Kode yang diketik pelanggan di checkout, beserta besar potongannya.</p>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-medium text-dark">Kode Kupon</label>
          <input type="text" name="code" value="{{ old('code', $coupon->code) }}" placeholder="HEMAT30" class="form-control form-control-sm" required style="text-transform:uppercase">
          @error('code') <p class="cf-err">{{ $message }}</p> @enderror
          <p class="cf-hint">Otomatis disimpan dalam huruf kapital.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-medium text-dark">Tipe Diskon</label>
          <select name="type" class="form-select" style="{{ $selectStyle }}">
            <option value="percent" @selected(old('type', $coupon->type ?? 'percent') === 'percent')>Persentase (%)</option>
            <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Nominal Tetap (Rp)</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-medium text-dark">Nilai Diskon</label>
          <input type="number" step="0.01" min="0.01" name="value" value="{{ old('value', $coupon->value) }}" class="form-control form-control-sm" required>
          @error('value') <p class="cf-err">{{ $message }}</p> @enderror
          <p class="cf-hint">Angka saja: 30 untuk 30%, atau 50000 untuk Rp 50.000.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-medium text-dark">Maks. Potongan (Rp, opsional)</label>
          <input type="number" step="1" min="0" name="max_discount" value="{{ old('max_discount', $coupon->max_discount) }}" class="form-control form-control-sm" placeholder="Tanpa batas">
          <p class="cf-hint">Berguna untuk tipe persentase, mis. "30% maks Rp 100.000".</p>
        </div>
      </div>
    </div>

    {{-- 2. Batas & periode --}}
    <div class="cf-card">
      <h2>Batas &amp; Periode</h2>
      <p class="cf-sub">Kapan kupon berlaku dan seberapa sering boleh dipakai.</p>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-medium text-dark">Min. Transaksi (Rp)</label>
          <input type="number" step="1" min="0" name="min_order" value="{{ old('min_order', $coupon->min_order ?? 0) }}" class="form-control form-control-sm">
          <p class="cf-hint">Dihitung dari subtotal produk yang berlaku saja, bukan seluruh keranjang.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-medium text-dark">Maks. Pemakaian per Klien</label>
          <input type="number" min="1" name="usage_limit_per_client" value="{{ old('usage_limit_per_client', $coupon->usage_limit_per_client ?? 1) }}" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-medium text-dark">Kuota Total (opsional)</label>
          <input type="number" min="1" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" class="form-control form-control-sm" placeholder="Tanpa batas">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-medium text-dark">Mulai Berlaku</label>
          <input type="date" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d')) }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-medium text-dark">Berlaku Sampai</label>
          <input type="date" name="expires_at" value="{{ old('expires_at', optional($coupon->expires_at)->format('Y-m-d')) }}" class="form-control form-control-sm">
          @error('expires_at') <p class="cf-err">{{ $message }}</p> @enderror
        </div>
      </div>
    </div>

    {{-- 3. Berlaku untuk --}}
    <div class="cf-card" id="scopeBox">
      <h2>Berlaku Untuk</h2>
      <p class="cf-sub">Pilih apakah kupon berlaku ke semua produk atau hanya sebagian.</p>

      <div class="cf-seg mb-3">
        <label class="{{ $appliesAll ? 'on' : '' }}"><input type="radio" name="applies_to" value="all" @checked($appliesAll) data-scope-radio> Semua Produk</label>
        <label class="{{ ! $appliesAll ? 'on' : '' }}"><input type="radio" name="applies_to" value="specific" @checked(! $appliesAll) data-scope-radio> Produk / Domain Tertentu</label>
      </div>

      <div id="scopeSpecific" class="{{ $appliesAll ? 'd-none' : '' }}">
        <div class="mb-3">
          <p class="cf-sub-title">Kategori <span class="fw-normal text-muted">(semua produk di dalamnya ikut berlaku)</span></p>
          <div class="d-flex flex-wrap gap-2">
            @forelse ($categories as $cat)
              <label class="cf-pill {{ in_array($cat->id, $checkedCats, true) ? 'on' : '' }}">
                <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $checkedCats, true)) class="form-check-input" data-pill>
                {{ $cat->name }}
              </label>
            @empty
              <p class="text-muted mb-0" style="font-size:12px">Belum ada kategori produk.</p>
            @endforelse
          </div>
        </div>

        <div class="mb-3">
          <p class="cf-sub-title">Atau produk spesifik <span class="fw-normal text-muted">(di luar kategori di atas)</span></p>
          <div class="d-flex flex-wrap gap-2 cf-scroll">
            @forelse ($products as $p)
              <label class="cf-pill {{ in_array($p->id, $checkedProds, true) ? 'on' : '' }}">
                <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, $checkedProds, true)) class="form-check-input" data-pill>
                {{ $p->name }}
              </label>
            @empty
              <p class="text-muted mb-0" style="font-size:12px">Belum ada produk.</p>
            @endforelse
          </div>
        </div>

        <div>
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <p class="cf-sub-title mb-0">Ekstensi domain <span class="fw-normal text-muted">(registrasi baru; harga ID Protection tidak ikut didiskon)</span></p>
            <button type="button" class="btn btn-outline-primary btn-sm" id="tldOpen">
              <i class="fa-solid fa-list-check" style="font-size:11px"></i> Pilih Ekstensi
            </button>
          </div>
          <div id="tldSummary" class="d-flex flex-wrap gap-2 align-items-center"></div>
          <div id="tldInputs"></div>
        </div>

        @error('scope') <p class="cf-err">{{ $message }}</p> @enderror
      </div>
    </div>

    {{-- 4. Halaman Promo publik --}}
    <div class="cf-card">
      <h2>Halaman Promo Publik</h2>
      <p class="cf-sub">Kode tetap harus dimasukkan pelanggan di checkout, tidak diterapkan otomatis. Banner halaman Promo diatur di menu Banner Promo (pilih halaman "Halaman Promo").</p>

      <label class="cf-switch mb-3">
        <input type="checkbox" name="is_public" value="1" id="isPublic" @checked($isPublic) class="form-check-input">
        <span>
          <span class="small fw-medium text-dark d-block">Tampilkan di halaman Promo publik</span>
          <span class="cf-hint">Kupon akan muncul sebagai kartu promo di <code>/promo</code> selama aktif dan masih dalam masa berlaku.</span>
        </span>
      </label>

      <div id="publicFields" class="{{ $isPublic ? '' : 'd-none' }}">
        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Judul Promo</label>
          <input type="text" name="title" maxlength="120" value="{{ old('title', $coupon->title) }}" class="form-control form-control-sm" placeholder="Diskon Domain .my.id">
          @error('title') <p class="cf-err">{{ $message }}</p> @enderror
        </div>
        <div>
          <label class="form-label small fw-medium text-dark">Keterangan</label>
          <textarea name="description" rows="3" maxlength="600" class="form-control form-control-sm" placeholder="Syarat singkat, mis. berlaku untuk registrasi baru 1 tahun.">{{ old('description', $coupon->description) }}</textarea>
          @error('description') <p class="cf-err">{{ $message }}</p> @enderror
        </div>
      </div>
    </div>

    <div class="cf-actions">
      <label class="d-flex align-items-center gap-2 small text-dark me-auto mb-0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true)) class="form-check-input" style="margin-top:0">
        Aktif
      </label>
      <a href="{{ route('admin.coupons') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
    </div>
  </form>

  {{-- Modal pemilih ekstensi domain --}}
  <div class="tm-back" id="tldModal" role="dialog" aria-modal="true" aria-labelledby="tldModalTitle">
    <div class="tm">
      <div class="tm-head">
        <h3 id="tldModalTitle">Pilih Ekstensi Domain</h3>
        <button type="button" class="tm-x" id="tldClose" aria-label="Tutup">&times;</button>
      </div>
      <div class="tm-tools">
        <input type="search" id="tmSearch" class="form-control form-control-sm" placeholder="Cari ekstensi, mis. .id atau .com">
        <select id="tmGroup" class="form-select" style="{{ $selectStyle }};max-width:10rem"><option value="">Semua grup</option></select>
        <select id="tmScope" class="form-select" style="{{ $selectStyle }};max-width:11rem">
          <option value="">Semua ekstensi</option>
          <option value="web">Tampil di Web</option>
          <option value="picked">Terpilih saja</option>
        </select>
      </div>
      <div class="tm-tools" style="padding-top:.5rem;padding-bottom:.5rem;background:#f9fafb">
        <span class="small text-muted" id="tmCount"></span>
        <span class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="tmAll">Pilih semua hasil</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="tmNone">Kosongkan hasil</button>
        </span>
      </div>
      <div class="tm-body" id="tmBody"></div>
      <div class="tm-foot">
        <span class="small text-muted"><strong id="tmPicked">0</strong> ekstensi dipilih</span>
        <span class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="tmCancel">Batal</button>
          <button type="button" class="btn btn-primary btn-sm" id="tmApply">Terapkan</button>
        </span>
      </div>
    </div>
  </div>

  <script type="application/json" id="tldData">{!! json_encode($tldData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
  <script type="application/json" id="tldSelected">{!! json_encode($checkedTlds) !!}</script>

  <script @nonce>
    (function () {
      var $ = function (id) { return document.getElementById(id); };
      var rp = function (n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); };

      // ── Ganti "Semua" / "Tertentu" ──
      var radios = document.querySelectorAll('[data-scope-radio]');
      function syncScope() {
        var active = document.querySelector('[data-scope-radio]:checked');
        $('scopeSpecific').classList.toggle('d-none', !active || active.value !== 'specific');
        radios.forEach(function (r) { r.closest('label').classList.toggle('on', r.checked); });
      }
      radios.forEach(function (r) { r.addEventListener('change', syncScope); });
      syncScope();

      // ── Pill kategori/produk ──
      document.querySelectorAll('[data-pill]').forEach(function (c) {
        c.addEventListener('change', function () { c.closest('label').classList.toggle('on', c.checked); });
      });

      // ── Toggle isian halaman Promo ──
      $('isPublic').addEventListener('change', function () {
        $('publicFields').classList.toggle('d-none', !this.checked);
      });

      // ── Pemilih ekstensi domain ──
      var tlds = JSON.parse($('tldData').textContent);
      var byId = {};
      tlds.forEach(function (t) { byId[t.id] = t; });

      var saved = new Set(JSON.parse($('tldSelected').textContent).filter(function (id) { return byId[id]; }));
      var draft = new Set(saved);

      var groups = Array.from(new Set(tlds.map(function (t) { return t.group; }))).sort();
      groups.forEach(function (g) {
        var o = document.createElement('option'); o.value = g; o.textContent = g; $('tmGroup').appendChild(o);
      });

      function renderSummary() {
        var box = $('tldSummary'), inputs = $('tldInputs');
        box.innerHTML = ''; inputs.innerHTML = '';
        var ids = Array.from(saved).sort(function (a, b) { return byId[a].ext.localeCompare(byId[b].ext); });

        ids.forEach(function (id) {
          var h = document.createElement('input');
          h.type = 'hidden'; h.name = 'tld_ids[]'; h.value = id; inputs.appendChild(h);
        });

        if (!ids.length) {
          box.innerHTML = '<span class="text-muted" style="font-size:12px">Belum ada ekstensi dipilih.</span>';
          return;
        }

        var LIMIT = 24;
        ids.slice(0, LIMIT).forEach(function (id) {
          var chip = document.createElement('span'); chip.className = 'cf-chip';
          chip.appendChild(document.createTextNode(byId[id].ext));
          var x = document.createElement('button'); x.type = 'button'; x.textContent = '×';
          x.setAttribute('aria-label', 'Hapus ' + byId[id].ext);
          x.addEventListener('click', function () { saved.delete(id); draft.delete(id); renderSummary(); });
          chip.appendChild(x); box.appendChild(chip);
        });
        if (ids.length > LIMIT) {
          var more = document.createElement('span');
          more.className = 'text-muted'; more.style.fontSize = '12px';
          more.textContent = '+' + (ids.length - LIMIT) + ' lainnya';
          box.appendChild(more);
        }
        var total = document.createElement('span');
        total.className = 'text-muted ms-1'; total.style.fontSize = '12px';
        total.textContent = '(' + ids.length + ' dipilih)';
        box.appendChild(total);
      }

      function filtered() {
        var q = $('tmSearch').value.trim().toLowerCase();
        var g = $('tmGroup').value, sc = $('tmScope').value;
        return tlds.filter(function (t) {
          if (q && t.ext.toLowerCase().indexOf(q) === -1) { return false; }
          if (g && t.group !== g) { return false; }
          if (sc === 'web' && !t.web) { return false; }
          if (sc === 'picked' && !draft.has(t.id)) { return false; }
          return true;
        });
      }

      function renderList() {
        var list = filtered(), body = $('tmBody');
        body.innerHTML = '';
        $('tmCount').textContent = list.length + ' ekstensi ditampilkan';
        $('tmPicked').textContent = draft.size;

        if (!list.length) {
          body.innerHTML = '<div class="tm-empty">Tidak ada ekstensi yang cocok.</div>';
          return;
        }

        var frag = document.createDocumentFragment();
        list.slice(0, 400).forEach(function (t) {
          var label = document.createElement('label');
          label.className = 'tm-item' + (draft.has(t.id) ? ' on' : '');
          var cb = document.createElement('input');
          cb.type = 'checkbox'; cb.className = 'form-check-input'; cb.checked = draft.has(t.id);
          cb.addEventListener('change', function () {
            if (cb.checked) { draft.add(t.id); } else { draft.delete(t.id); }
            label.classList.toggle('on', cb.checked);
            $('tmPicked').textContent = draft.size;
          });
          var txt = document.createElement('span');
          txt.innerHTML = '<b></b><small></small>';
          txt.firstChild.textContent = t.ext;
          txt.lastChild.textContent = rp(t.price) + ' · ' + t.reg;
          label.appendChild(cb); label.appendChild(txt); frag.appendChild(label);
        });
        body.appendChild(frag);

        if (list.length > 400) {
          var note = document.createElement('div');
          note.className = 'tm-empty';
          note.textContent = 'Menampilkan 400 pertama. Persempit dengan pencarian atau filter grup.';
          body.appendChild(note);
        }
      }

      function open() {
        draft = new Set(saved);
        $('tmSearch').value = ''; $('tmGroup').value = ''; $('tmScope').value = '';
        renderList();
        $('tldModal').classList.add('open');
        $('tmSearch').focus();
      }
      function close() { $('tldModal').classList.remove('open'); }

      $('tldOpen').addEventListener('click', open);
      $('tldClose').addEventListener('click', close);
      $('tmCancel').addEventListener('click', close);
      $('tldModal').addEventListener('mousedown', function (e) { if (e.target === this) { close(); } });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });

      $('tmSearch').addEventListener('input', renderList);
      $('tmGroup').addEventListener('change', renderList);
      $('tmScope').addEventListener('change', renderList);
      $('tmAll').addEventListener('click', function () { filtered().forEach(function (t) { draft.add(t.id); }); renderList(); });
      $('tmNone').addEventListener('click', function () { filtered().forEach(function (t) { draft.delete(t.id); }); renderList(); });
      $('tmApply').addEventListener('click', function () { saved = new Set(draft); renderSummary(); close(); });

      renderSummary();
    })();
  </script>

@endsection
