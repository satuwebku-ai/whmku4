@extends('layouts.admin')

@section('title', $client->exists ? 'Edit Klien' : 'Tambah Klien')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $client->exists ? 'Edit Klien' : 'Tambah Klien Baru' }}</h1>
    <p class="small text-muted mb-0">Lengkapi data pelanggan di bawah ini.</p>
  </div>

  <form method="POST" action="{{ $client->exists ? route('admin.client.update', $client) : route('admin.client.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($client->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama Lengkap</label>
        <input type="text" name="name" value="{{ old('name', $client->name) }}" class="form-control form-control-sm" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Email</label>
        <input type="email" name="email" value="{{ old('email', $client->email) }}" class="form-control form-control-sm" required>
        @error('email') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">No. Telepon</label>
        <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" class="form-control form-control-sm">
        @error('phone') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Perusahaan (opsional)</label>
        <input type="text" name="company" value="{{ old('company', $client->company) }}" class="form-control form-control-sm">
        @error('company') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Alamat</label>
      <textarea name="address" rows="2" class="form-control form-control-sm">{{ old('address', $client->address) }}</textarea>
      @error('address') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Kota</label>
        <input type="text" name="city" value="{{ old('city', $client->city) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Negara</label>
        <input type="text" name="country" value="{{ old('country', $client->country ?? 'Indonesia') }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Status</label>
        <select name="status" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
          <option value="active" @selected(old('status', $client->status) === 'active')>Aktif</option>
          <option value="inactive" @selected(old('status', $client->status) === 'inactive')>Nonaktif</option>
        </select>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-check" style="font-size:11px"></i> {{ $client->exists ? 'Simpan Perubahan' : 'Simpan Klien' }}
      </button>
      <a href="{{ route('admin.clients') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
