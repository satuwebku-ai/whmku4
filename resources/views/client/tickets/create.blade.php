@extends('client.layout')
@section('title', 'Buat Tiket')

@section('content')
  <a href="{{ route('client.tickets') }}" class="text-decoration-none text-muted" style="font-size:12px">&larr; Kembali ke Tiket</a>

  <h1 class="h4 fw-bold text-dark mt-2 mb-1">Buat Tiket Support</h1>
  <p class="text-muted mb-4">Jelaskan kendala Anda sedetail mungkin agar kami bisa membantu lebih cepat.</p>

  @if ($errors->any())
    <div class="rounded-3 px-3 py-2 mb-4" style="background:#fef2f2;border:1px solid #fecaca;font-size:14px;color:#b91c1c">
      <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $error)<li style="margin-bottom:.25rem">{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('client.tickets.store') }}" enctype="multipart/form-data" class="card-public p-4 d-flex flex-column gap-3" style="max-width:42rem">
    @csrf

    <div>
      <label class="form-label">Subjek</label>
      <input type="text" name="subject" value="{{ old('subject') }}" required class="form-control" placeholder="Website tidak bisa diakses">
    </div>

    <div class="row g-3">
      <div class="col-sm-6">
        <label class="form-label">Departemen</label>
        <select name="department" class="form-select">
          <option value="support" @selected(old('department') === 'support')>Bantuan Teknis</option>
          <option value="billing" @selected(old('department') === 'billing')>Tagihan &amp; Pembayaran</option>
          <option value="sales" @selected(old('department') === 'sales')>Penjualan</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label">Prioritas</label>
        <select name="priority" class="form-select">
          <option value="low" @selected(old('priority') === 'low')>Rendah</option>
          <option value="medium" @selected(old('priority', 'medium') === 'medium')>Sedang</option>
          <option value="high" @selected(old('priority') === 'high')>Tinggi</option>
        </select>
      </div>
    </div>

    @if ($services->isNotEmpty())
      <div>
        <label class="form-label">Layanan Terkait <span class="text-muted fw-normal">(opsional)</span></label>
        <select name="hosting_account_id" class="form-select">
          <option value="">— Tidak terkait layanan tertentu —</option>
          @foreach ($services as $service)
            <option value="{{ $service->id }}" @selected(old('hosting_account_id') == $service->id)>{{ $service->domain }} ({{ $service->package }})</option>
          @endforeach
        </select>
      </div>
    @endif

    <div>
      <label class="form-label">Pesan</label>
      <textarea name="message" rows="7" required class="form-control" placeholder="Ceritakan kendala Anda...">{{ old('message') }}</textarea>
    </div>

    <div>
      <label class="form-label">Lampiran <span class="text-muted fw-normal">(opsional, maks 5 berkas @ 5MB)</span></label>
      <input type="file" name="attachments[]" multiple class="form-control form-control-sm">
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Format: jpg, png, pdf, txt, log, zip. Screenshot error sangat membantu. Bisa pilih beberapa berkas sekaligus.</p>
      @error('attachments') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      @error('attachments.*') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="d-flex gap-2 pt-2">
      <button type="submit" class="btn btn-theme"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Tiket</button>
      <a href="{{ route('client.tickets') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </form>
@endsection
