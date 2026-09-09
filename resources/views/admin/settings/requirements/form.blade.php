@extends('layouts.admin')
@section('title', $requirement->exists ? 'Edit Persyaratan' : 'Tambah Persyaratan')
@section('content')
  @include('admin.settings._nav')

  <div class="mb-4">
    <a href="{{ route('admin.settings.requirements.index') }}" class="text-decoration-none text-muted" style="font-size:12px">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Persyaratan
    </a>
    <h1 class="h4 fw-bold text-dark mt-1 mb-0">{{ $requirement->exists ? 'Edit Persyaratan' : 'Tambah Persyaratan' }}</h1>
  </div>

  <form method="POST"
        action="{{ $requirement->exists ? route('admin.settings.requirements.update', $requirement) : route('admin.settings.requirements.store') }}"
        class="card border rounded-4 p-4" style="max-width:38rem">
    @csrf
    @if ($requirement->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Berkas</label>
      <input type="text" name="name" value="{{ old('name', $requirement->name) }}"
             placeholder="mis. KTP Penanggung Jawab" class="form-control form-control-sm" required>
      @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Petunjuk untuk Klien</label>
      <textarea name="description" rows="3" class="form-control form-control-sm"
                placeholder="Dijelaskan ke klien saat mengunggah — mis. format file, siapa yang harus tercantum.">{{ old('description', $requirement->description) }}</textarea>
      @error('description') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
      <input type="number" name="sort_order" min="0" max="999"
             value="{{ old('sort_order', $requirement->sort_order ?? 0) }}"
             class="form-control form-control-sm" style="max-width:8rem">
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Angka kecil tampil lebih dulu.</p>
    </div>

    <div class="d-flex flex-column gap-2 pt-3 border-top">
      <label class="d-flex align-items-start gap-2" style="cursor:pointer">
        <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $requirement->is_required ?? true)) class="form-check-input" style="margin-top:.15rem">
        <span>
          <span class="d-block fw-medium text-dark" style="font-size:13px">Wajib diunggah</span>
          <span class="d-block text-muted" style="font-size:11px">
            Kalau dimatikan, berkas ini tetap ditawarkan ke klien tapi domain tetap bisa diproses walau tidak diunggah
            (mis. "Sertifikat merek, kalau ada").
          </span>
        </span>
      </label>

      <label class="d-flex align-items-start gap-2" style="cursor:pointer">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $requirement->is_active ?? true)) class="form-check-input" style="margin-top:.15rem">
        <span>
          <span class="d-block fw-medium text-dark" style="font-size:13px">Aktif</span>
          <span class="d-block text-muted" style="font-size:11px">
            Persyaratan nonaktif tidak diminta ke klien baru, tapi berkas yang sudah pernah diunggah tetap tersimpan.
          </span>
        </span>
      </label>
    </div>

    <div class="d-flex align-items-center gap-2 pt-3 mt-3 border-top">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.settings.requirements.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>
@endsection
