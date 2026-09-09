@extends('layouts.admin')

@section('title', 'Buat Tiket')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Buat Tiket</h1>
    <p class="small text-muted mb-0">Untuk mencatat keluhan klien yang masuk lewat jalur lain (telepon, WhatsApp, dsb).</p>
  </div>

  <form method="POST" action="{{ route('admin.ticket.add') }}" enctype="multipart/form-data" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Klien</label>
      <select name="client_id" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem" required>
        <option value="">Pilih klien</option>
        @foreach ($clients as $client)
          <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }} ({{ $client->email }})</option>
        @endforeach
      </select>
      @error('client_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Subjek</label>
      <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Website tidak bisa diakses" class="form-control form-control-sm" required>
      @error('subject') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Departemen</label>
        <select name="department" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
          <option value="support" @selected(old('department') === 'support')>Technical Support</option>
          <option value="billing" @selected(old('department') === 'billing')>Billing</option>
          <option value="sales" @selected(old('department') === 'sales')>Sales</option>
          <option value="abuse" @selected(old('department') === 'abuse')>Abuse</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Prioritas</label>
        <select name="priority" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
          <option value="low" @selected(old('priority') === 'low')>Low</option>
          <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium</option>
          <option value="high" @selected(old('priority') === 'high')>High</option>
          <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Pesan / Keluhan</label>
      <textarea name="message" rows="5" class="form-control form-control-sm" placeholder="Tuliskan keluhan klien..." required>{{ old('message') }}</textarea>
      @error('message') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Lampiran <span class="text-muted fw-normal">(opsional, maks 5 berkas @ 5MB)</span></label>
      <input type="file" name="attachments[]" multiple class="form-control form-control-sm">
      @error('attachments') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      @error('attachments.*') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Buat Tiket</button>
      <a href="{{ route('admin.tickets') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
