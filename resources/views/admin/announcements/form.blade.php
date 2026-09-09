@extends('layouts.admin')

@section('title', $announcement->exists ? 'Edit Pengumuman' : 'Buat Pengumuman')

@section('content')

  @include('admin.pages._nav')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $announcement->exists ? 'Edit Pengumuman' : 'Buat Pengumuman' }}</h1>
    @if ($announcement->exists)
      <p class="small text-muted mb-0">
        URL: <a href="{{ route('announcements.show', $announcement->slug) }}" target="_blank" class="text-accent">{{ route('announcements.show', $announcement->slug) }}</a>
      </p>
    @endif
  </div>

  <form method="POST" action="{{ $announcement->exists ? route('admin.announcement.update', $announcement) : route('admin.announcement.add') }}" class="row g-3" style="max-width:70rem">
    @csrf

    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 p-4 mb-3">
        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Judul</label>
          <input type="text" name="title" id="nameInput" value="{{ old('title', $announcement->title) }}" class="form-control form-control-sm" required>
          @error('title') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Slug URL</label>
          <input type="text" name="slug" id="slugInput" value="{{ old('slug', $announcement->slug) }}" placeholder="otomatis dari judul" class="form-control form-control-sm">
          @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Ringkasan (opsional)</label>
          <textarea name="excerpt" rows="2" maxlength="500" class="form-control form-control-sm" placeholder="Ditampilkan di daftar pengumuman">{{ old('excerpt', $announcement->excerpt) }}</textarea>
        </div>

        <div>
          <label class="form-label small fw-medium text-dark">Isi Pengumuman</label>
          <textarea name="content" rows="12" class="form-control form-control-sm" style="font-family:monospace;font-size:12px" required>{{ old('content', $announcement->content) }}</textarea>
          @error('content') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          <p class="text-muted mt-1 mb-0" style="font-size:11px">Mendukung HTML dasar.</p>
        </div>
      </div>

      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">SEO (opsional)</h2>
        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Meta Title</label>
          <input type="text" name="meta_title" maxlength="70" value="{{ old('meta_title', $announcement->meta_title) }}" class="form-control form-control-sm" placeholder="Kosongkan untuk memakai judul">
        </div>
        <div>
          <label class="form-label small fw-medium text-dark">Meta Description</label>
          <textarea name="meta_description" rows="2" maxlength="170" class="form-control form-control-sm" placeholder="Kosongkan untuk memakai ringkasan">{{ old('meta_description', $announcement->meta_description) }}</textarea>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Publikasi</h2>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Kategori</label>
          <select name="category" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
            <option value="info" @selected(old('category', $announcement->category ?? 'info') === 'info')>Info</option>
            <option value="promo" @selected(old('category', $announcement->category) === 'promo')>Promo</option>
            <option value="maintenance" @selected(old('category', $announcement->category) === 'maintenance')>Maintenance</option>
            <option value="incident" @selected(old('category', $announcement->category) === 'incident')>Gangguan</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Jadwal Terbit</label>
          <input type="datetime-local" name="published_at"
                 value="{{ old('published_at', optional($announcement->published_at)->format('Y-m-d\TH:i')) }}"
                 class="form-control form-control-sm">
          <p class="text-muted mt-1 mb-0" style="font-size:11px">Kosongkan untuk terbit sekarang. Isi tanggal ke depan untuk menjadwalkan.</p>
        </div>

        <label class="d-flex align-items-center gap-2 small text-dark mb-2">
          <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $announcement->is_published ?? true)) class="form-check-input" style="margin-top:0">
          Terbitkan
        </label>

        <label class="d-flex align-items-center gap-2 small text-dark mb-3">
          <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned)) class="form-check-input" style="margin-top:0">
          Sematkan di atas
        </label>

        <div class="d-flex flex-column gap-2 pt-2 border-top">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
          <a href="{{ route('admin.announcements') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        </div>
      </div>
    </div>
  </form>

  <script>
    (function () {
      const name = document.getElementById('nameInput');
      const slug = document.getElementById('slugInput');

      const slugify = (s) => s.toLowerCase().trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

      let slugTouched = slug.value.length > 0;
      slug.addEventListener('input', () => { slugTouched = true; });
      name.addEventListener('input', () => {
        if (!slugTouched) slug.value = slugify(name.value);
      });
    })();
  </script>

@endsection
