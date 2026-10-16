@extends('layouts.admin')

@section('title', $post->exists ? 'Edit Artikel Blog' : 'Artikel Blog Baru')

@section('content')
  @include('admin.pages._nav')
  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $post->exists ? 'Edit Artikel Blog' : 'Artikel Blog Baru' }}</h1>
    <p class="small text-muted mb-0">Konten HTML akan dibersihkan saat ditampilkan ke pengunjung.</p>
  </div>

  <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}" class="row g-3" style="max-width:72rem">
    @csrf
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 p-4">
        <div class="mb-3">
          <label class="form-label small fw-medium">Judul</label>
          <input name="title" value="{{ old('title', $post->title) }}" class="form-control" maxlength="255" required>
          @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Slug URL</label>
          <input name="slug" value="{{ old('slug', $post->slug) }}" class="form-control" maxlength="255" placeholder="Dibuat otomatis dari judul jika dikosongkan">
          @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Ringkasan</label>
          <textarea name="excerpt" rows="3" maxlength="255" class="form-control">{{ old('excerpt', $post->excerpt) }}</textarea>
          @error('excerpt')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Konten</label>
          <textarea name="content" rows="18" class="form-control" style="font-family:monospace;font-size:13px" required>{{ old('content', $post->content) }}</textarea>
          <div class="form-text">Boleh menggunakan HTML sederhana seperti heading, paragraf, daftar, tautan, dan gambar. Script berbahaya akan dibuang.</div>
          @error('content')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label small fw-medium">URL gambar sampul (opsional)</label>
          <input type="url" name="cover_image" value="{{ old('cover_image', $post->cover_image) }}" class="form-control" maxlength="255" placeholder="https://contoh.com/gambar.jpg">
          @error('cover_image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="h6 fw-bold mb-3">Publikasi</h2>
        <div class="mb-3">
          <label class="form-label small fw-medium">Kategori</label>
          <select name="cms_category_id" class="form-select">
            <option value="">Tanpa kategori</option>
            @foreach ($categories as $category)
              <option value="{{ $category->id }}" @selected((string) old('cms_category_id', $post->cms_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
          </select>
          @error('cms_category_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Status</label>
          <select name="status" class="form-select" required>
            <option value="draft" @selected(old('status', $post->status ?: 'draft') === 'draft')>Draf</option>
            <option value="published" @selected(old('status', $post->status) === 'published')>Terbit</option>
          </select>
          @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Tanggal terbit</label>
          <input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="form-control">
          <div class="form-text">Tanggal mendatang akan menjadwalkan artikel; artikel baru terlihat setelah waktunya tiba.</div>
          @error('published_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex flex-column gap-2 pt-3 border-top">
          <button class="btn btn-primary">Simpan Artikel</button>
          <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
      </div>
    </div>
  </form>
@endsection
