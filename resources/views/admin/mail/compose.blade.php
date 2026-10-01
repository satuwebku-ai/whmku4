@extends('layouts.admin')

@section('title', 'Tulis Email')

@section('content')

  <div class="mb-4">
    <a href="{{ route('admin.mail') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Email</a>
    <h1 class="h4 fw-bold text-dark mt-1 mb-1">Tulis Email</h1>
    <p class="small text-muted mb-0">Email dikirim dari alamat pengirim di Pengaturan → Email. Balasan pelanggan masuk ke Kotak Masuk sebagai thread yang sama.</p>
  </div>

  <div class="card border rounded-4" style="max-width:52rem">
    <form method="POST" action="{{ route('admin.mail.store') }}" enctype="multipart/form-data" class="px-4 py-4">
      @csrf

      <div class="row g-3 mb-3">
        <div class="col-md-7">
          <label for="toEmail" class="form-label small fw-medium">Kepada (email)</label>
          <input type="email" id="toEmail" name="to_email" value="{{ old('to_email', $to) }}" maxlength="255" placeholder="pelanggan@contoh.com"
                 class="form-control form-control-sm @error('to_email') is-invalid @enderror" required>
          @error('to_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
          <label for="toName" class="form-label small fw-medium">Nama <span class="text-muted fw-normal">(opsional)</span></label>
          <input type="text" id="toName" name="to_name" value="{{ old('to_name') }}" maxlength="120"
                 class="form-control form-control-sm @error('to_name') is-invalid @enderror">
          @error('to_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="mb-3">
        <label for="mailSubject" class="form-label small fw-medium">Subjek</label>
        <input type="text" id="mailSubject" name="subject" value="{{ old('subject') }}" maxlength="200"
               class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="mb-3">
        <label for="mailBody" class="form-label small fw-medium">Pesan</label>
        <textarea id="mailBody" name="body" rows="10" maxlength="20000"
                  class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="mb-4">
        <label for="mailFiles" class="form-label small fw-medium">Lampiran <span class="text-muted fw-normal">(opsional, maks. 5 berkas @ 5 MB: JPG, PNG, WEBP, PDF, TXT, ZIP)</span></label>
        <input type="file" id="mailFiles" name="attachments[]" multiple
               class="form-control form-control-sm @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror">
        @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('attachments.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.mail') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Email</button>
      </div>
    </form>
  </div>

@endsection
