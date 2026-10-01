@php $id = $t?->id ?? 'new'; @endphp
<div class="row g-2">
  <div class="col-md-7">
    <label class="form-label small fw-medium">Judul</label>
    <input type="text" name="title" value="{{ old('title', $t?->title) }}" maxlength="120" required class="form-control form-control-sm" placeholder="mis. Konfirmasi pembayaran">
    @error('title')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
  </div>
  <div class="col-md-5">
    <label class="form-label small fw-medium">Kategori <span class="text-muted fw-normal">(opsional)</span></label>
    <input type="text" name="category" value="{{ old('category', $t?->category) }}" maxlength="60" list="tplCats{{ $id }}" class="form-control form-control-sm" placeholder="Pembayaran, Teknis, Umum…">
    <datalist id="tplCats{{ $id }}">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
  </div>
  <div class="col-12">
    <label class="form-label small fw-medium">Subjek email <span class="text-muted fw-normal">(opsional, mengisi subjek saat dipakai di email)</span></label>
    <input type="text" name="subject" value="{{ old('subject', $t?->subject) }}" maxlength="200" class="form-control form-control-sm">
  </div>
  <div class="col-12">
    <label class="form-label small fw-medium">Isi template</label>
    <textarea name="body" rows="7" maxlength="5000" required class="form-control form-control-sm" placeholder="Halo {nama}, …">{{ old('body', $t?->body) }}</textarea>
    @error('body')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
  </div>
  <div class="col-12 d-flex flex-wrap gap-4">
    <label class="d-flex align-items-center gap-2 small mb-0">
      <input type="checkbox" class="form-check-input mt-0" name="is_active" value="1" @checked(old('is_active', $t?->is_active ?? true))> Aktif (tampil di pilihan template)
    </label>
    <label class="d-flex align-items-center gap-2 small mb-0">
      <input type="checkbox" class="form-check-input mt-0" name="use_for_ai" value="1" @checked(old('use_for_ai', $t?->use_for_ai ?? false))> Dipakai AI sebagai panduan jawaban
    </label>
  </div>
</div>
