@extends('layouts.admin')

@section('title', $article->exists ? 'Edit Artikel Bantuan' : 'Artikel Bantuan Baru')

@section('content')
  @include('admin.pages._nav')
  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $article->exists ? 'Edit Artikel Bantuan' : 'Artikel Bantuan Baru' }}</h1>
    <p class="small text-muted mb-0">Tulis jawaban yang jelas dan mudah diikuti. HTML berbahaya dibuang saat ditampilkan.</p>
  </div>

  <form method="POST" action="{{ $article->exists ? route('admin.knowledge-base.update', $article) : route('admin.knowledge-base.store') }}" class="row g-3" style="max-width:72rem">
    @csrf
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 p-4">
        <div class="mb-3">
          <label class="form-label small fw-medium">Judul panduan</label>
          <input name="title" value="{{ old('title', $article->title) }}" class="form-control" maxlength="255" required>
          @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Slug URL</label>
          <input name="slug" value="{{ old('slug', $article->slug) }}" class="form-control" maxlength="255" placeholder="Dibuat otomatis dari judul jika dikosongkan">
          @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label small fw-medium">Isi artikel</label>
          <textarea name="content" rows="20" class="form-control" style="font-family:monospace;font-size:13px" required>{{ old('content', $article->content) }}</textarea>
          <div class="form-text">Boleh menggunakan HTML sederhana. Hindari memasukkan script atau kode dari sumber yang tidak dipercaya.</div>
          @error('content')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="h6 fw-bold mb-3">Pengaturan</h2>
        <div class="mb-3">
          <label class="form-label small fw-medium">Kategori</label>
          <select name="knowledge_base_category_id" class="form-select">
            <option value="">Tanpa kategori</option>
            @foreach ($categories as $category)
              <option value="{{ $category->id }}" @selected((string) old('knowledge_base_category_id', $article->knowledge_base_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
          </select>
          @error('knowledge_base_category_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <label class="d-flex align-items-center gap-2 small mb-3">
          <input type="checkbox" name="is_published" value="1" class="form-check-input" @checked(old('is_published', $article->is_published))>
          Terbitkan artikel
        </label>
        <div class="d-flex flex-column gap-2 pt-3 border-top">
          <button class="btn btn-primary">Simpan Artikel</button>
          <a href="{{ route('admin.knowledge-base.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
      </div>
    </div>
  </form>
@endsection
