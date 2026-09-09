@extends('layouts.admin')

@section('title', $page->exists ? 'Edit Halaman' : 'Tambah Halaman')

@section('content')

  @include('admin.pages._nav')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $page->exists ? 'Edit Halaman' : 'Tambah Halaman' }}</h1>
    @if ($page->exists)
      <p class="small text-muted mb-0">
        URL: <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="text-accent">{{ $page->url }}</a>
      </p>
    @endif
  </div>

  <form method="POST" action="{{ $page->exists ? route('admin.page.update', $page) : route('admin.page.add') }}" class="row g-3" style="max-width:70rem">
    @csrf

    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 p-4 mb-3">
        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Judul Halaman</label>
          <input type="text" name="title" id="titleInput" value="{{ old('title', $page->title) }}" class="form-control form-control-sm" required>
          @error('title') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Slug URL</label>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted flex-shrink-0" style="font-size:12px">{{ url('/') }}/</span>
            <input type="text" name="slug" id="slugInput" value="{{ old('slug', $page->slug) }}" placeholder="otomatis dari judul" class="form-control form-control-sm">
          </div>
          @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          <p class="text-muted mt-1 mb-0" style="font-size:11px">
            Kosongkan untuk dibuat otomatis dari judul. Hati-hati mengubah slug halaman yang sudah terbit — link lama akan mati.
            Beberapa kata seperti "admin", "hosting", "keranjang" tidak bisa dipakai karena sudah menjadi alamat fitur sistem.
          </p>
        </div>

        <div>
          <label class="form-label small fw-medium text-dark">Konten</label>
          <textarea name="content" rows="16" class="form-control form-control-sm" style="font-family:monospace;font-size:12px">{{ old('content', $page->content) }}</textarea>
          @error('content') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          <p class="text-muted mt-1 mb-0" style="font-size:11px">
            Mendukung HTML (<code>&lt;h2&gt;</code>, <code>&lt;p&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;a&gt;</code>, dsb).
            Konten ditampilkan apa adanya ke pengunjung, jadi jangan tempel HTML dari sumber yang tidak dipercaya.
          </p>
        </div>
      </div>

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Pengaturan SEO</h2>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Meta Title</label>
          <input type="text" name="meta_title" id="metaTitle" maxlength="70" value="{{ old('meta_title', $page->meta_title) }}" class="form-control form-control-sm" placeholder="Kosongkan untuk memakai judul halaman">
          <p class="text-muted mt-1 mb-0" style="font-size:11px"><span id="metaTitleCount">0</span>/70 karakter — idealnya di bawah 60 supaya tidak terpotong di Google.</p>
          @error('meta_title') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Meta Description</label>
          <textarea name="meta_description" id="metaDesc" rows="3" maxlength="170" class="form-control form-control-sm" placeholder="Ringkasan singkat isi halaman untuk hasil pencarian">{{ old('meta_description', $page->meta_description) }}</textarea>
          <p class="text-muted mt-1 mb-0" style="font-size:11px"><span id="metaDescCount">0</span>/170 karakter — idealnya 120–155.</p>
          @error('meta_description') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label small fw-medium text-dark">Meta Keywords (opsional)</label>
            <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $page->meta_keywords) }}" class="form-control form-control-sm" placeholder="hosting murah, domain id">
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Google mengabaikan tag ini sejak lama; isi hanya kalau butuh untuk mesin pencari lain.</p>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-medium text-dark">OG Image URL (opsional)</label>
            <input type="text" name="og_image" value="{{ old('og_image', $page->og_image) }}" class="form-control form-control-sm" placeholder="https://...">
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Gambar saat link dibagikan ke media sosial. Ukuran ideal 1200×630.</p>
          </div>
        </div>

        {{-- Pratinjau hasil pencarian --}}
        <div class="rounded-3 border p-3" style="background:#f8fafc">
          <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em">Pratinjau di Google</p>
          <p class="text-truncate mb-0" style="font-size:13px;color:#15803d">{{ url('/') }}/<span id="previewSlug">{{ $page->slug ?: 'slug-halaman' }}</span></p>
          <p class="text-truncate mb-0" style="font-size:18px;color:#1a0dab;line-height:1.3" id="previewTitle">{{ $page->seo_title ?: 'Judul Halaman' }}</p>
          <p class="mb-0" style="font-size:13px;color:#475569;line-height:1.4" id="previewDesc">{{ $page->seo_description ?: 'Deskripsi halaman akan muncul di sini.' }}</p>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Publikasi</h2>

        <label class="d-flex align-items-center gap-2 small text-dark mb-2">
          <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published ?? true)) class="form-check-input" style="margin-top:0">
          Terbitkan halaman
        </label>

        <label class="d-flex align-items-center gap-2 small text-dark mb-2">
          <input type="checkbox" name="show_in_footer" value="1" @checked(old('show_in_footer', $page->show_in_footer)) class="form-check-input" style="margin-top:0">
          Tampilkan link di footer
        </label>

        <label class="d-flex align-items-center gap-2 small text-dark mb-3">
          <input type="checkbox" name="noindex" value="1" @checked(old('noindex', $page->noindex)) class="form-check-input" style="margin-top:0">
          Sembunyikan dari mesin pencari (noindex)
        </label>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Urutan di Footer</label>
          <input type="number" name="sort_order" value="{{ old('sort_order', $page->sort_order ?? 0) }}" class="form-control form-control-sm">
        </div>

        <div class="d-flex flex-column gap-2 pt-2 border-top">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Halaman</button>
          <a href="{{ route('admin.pages') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        </div>
      </div>
    </div>
  </form>

  <script>
    (function () {
      const title     = document.getElementById('titleInput');
      const slug      = document.getElementById('slugInput');
      const metaTitle = document.getElementById('metaTitle');
      const metaDesc  = document.getElementById('metaDesc');

      const pvSlug  = document.getElementById('previewSlug');
      const pvTitle = document.getElementById('previewTitle');
      const pvDesc  = document.getElementById('previewDesc');
      const ctTitle = document.getElementById('metaTitleCount');
      const ctDesc  = document.getElementById('metaDescCount');

      const slugify = (s) => s.toLowerCase().trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

      // Slug hanya diisi otomatis kalau halaman baru dan belum disentuh manual,
      // supaya slug halaman lama tidak berubah tanpa sengaja.
      let slugTouched = slug.value.length > 0;
      slug.addEventListener('input', () => { slugTouched = true; });

      function sync() {
        if (!slugTouched) slug.value = slugify(title.value);

        pvSlug.textContent  = slug.value || 'slug-halaman';
        pvTitle.textContent = metaTitle.value || title.value || 'Judul Halaman';
        pvDesc.textContent  = metaDesc.value || 'Deskripsi halaman akan muncul di sini.';
        ctTitle.textContent = metaTitle.value.length;
        ctDesc.textContent  = metaDesc.value.length;
      }

      [title, slug, metaTitle, metaDesc].forEach(el => el.addEventListener('input', sync));
      sync();
    })();
  </script>

@endsection
