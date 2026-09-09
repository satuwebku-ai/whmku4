@extends('layouts.admin')

@section('title', $domain->exists ? 'Edit Domain' : 'Tambah Domain')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $domain->exists ? 'Edit Domain' : 'Tambah Domain' }}</h1>
    <p class="small text-muted mb-0">
      @if ($domain->exists && $domain->provision_message)
        Status registrasi terakhir:
        <span class="fw-medium {{ $domain->provision_status === 'registered' ? 'text-success' : ($domain->provision_status === 'failed' ? 'text-danger' : 'text-muted') }}">
          {{ $domain->provision_message }}
        </span>
      @else
        Centang "Registrasi Otomatis" untuk langsung mendaftarkan domain lewat registrar.
      @endif
    </p>
  </div>

  <form method="POST" action="{{ $domain->exists ? route('admin.domain.update', $domain) : route('admin.domain.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($domain->exists) @method('PUT') @endif

    @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Klien</label>
        <select name="client_id" class="form-select" style="{{ $selectStyle }}" required>
          <option value="">Pilih klien</option>
          @foreach ($clients as $client)
            <option value="{{ $client->id }}" @selected(old('client_id', $domain->client_id) == $client->id)>{{ $client->name }}</option>
          @endforeach
        </select>
        @error('client_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama Domain</label>
        <input type="text" name="domain_name" value="{{ old('domain_name', $domain->domain_name ?? request('domain')) }}" placeholder="contoh.com" class="form-control form-control-sm" required>
        @error('domain_name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Registrar (opsional)</label>
        <select name="registrar_id" class="form-select" style="{{ $selectStyle }}">
          <option value="">— Manual —</option>
          @foreach ($registrars as $r)
            <option value="{{ $r->id }}" @selected(old('registrar_id', $domain->registrar_id) == $r->id)>{{ $r->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">TLD (opsional, untuk harga)</label>
        <select name="tld_id" class="form-select" style="{{ $selectStyle }}">
          <option value="">—</option>
          @foreach ($tlds as $t)
            <option value="{{ $t->id }}" @selected(old('tld_id', $domain->tld_id) == $t->id)>{{ $t->extension }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Lama (tahun)</label>
        <input type="number" name="years" min="1" max="10" value="{{ old('years', $domain->years ?? 1) }}" class="form-control form-control-sm" required>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Harga</label>
        <input type="number" step="0.01" name="price" value="{{ old('price', $domain->price) }}" class="form-control form-control-sm" required>
        @error('price') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Jatuh Tempo (kalau sudah tahu)</label>
        <input type="date" name="expiry_date" value="{{ old('expiry_date', optional($domain->expiry_date)->format('Y-m-d')) }}" class="form-control form-control-sm">
      </div>
    </div>

    <div class="d-flex align-items-center gap-4 mb-3">
      <label class="d-flex align-items-center gap-2 small text-dark" style="cursor:pointer">
        <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $domain->auto_renew ?? true))>
        Auto Renew
      </label>
      <label class="d-flex align-items-center gap-2 small text-dark" style="cursor:pointer">
        <input type="checkbox" name="whois_privacy" value="1" @checked(old('whois_privacy', $domain->whois_privacy))>
        WHOIS Privacy
      </label>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Status</label>
      <select name="status" class="form-select" style="{{ $selectStyle }};max-width:16rem">
        <option value="pending" @selected(old('status', $domain->status) === 'pending')>Pending</option>
        <option value="active" @selected(old('status', $domain->status) === 'active')>Aktif</option>
        <option value="expired" @selected(old('status', $domain->status) === 'expired')>Expired</option>
        <option value="cancelled" @selected(old('status', $domain->status) === 'cancelled')>Cancelled</option>
      </select>
    </div>

    @unless ($domain->exists)
      <div class="rounded-3 border border-primary p-3 mb-3" style="border-style:dashed!important;background:rgba(79,70,229,.05)">
        <label class="d-flex align-items-center gap-2 small fw-bold text-accent mb-2" style="cursor:pointer">
          <input type="checkbox" name="register_now" value="1" id="registerNow" @checked(old('register_now'))>
          Registrasi Otomatis lewat Registrar Sekarang
        </label>

        <p class="text-muted mb-3" style="font-size:11px">Wajib diisi kalau opsi di atas dicentang — registrar butuh data kontak WHOIS lengkap.</p>

        <div class="row g-2 mb-2">
          <div class="col-sm-6"><input type="text" name="contact_first_name" value="{{ old('contact_first_name') }}" placeholder="Nama Depan" class="form-control form-control-sm"></div>
          <div class="col-sm-6"><input type="text" name="contact_last_name" value="{{ old('contact_last_name') }}" placeholder="Nama Belakang" class="form-control form-control-sm"></div>
        </div>
        <input type="text" name="contact_address" value="{{ old('contact_address') }}" placeholder="Alamat" class="form-control form-control-sm mb-2">
        <div class="row g-2 mb-2">
          <div class="col-sm-4"><input type="text" name="contact_city" value="{{ old('contact_city') }}" placeholder="Kota" class="form-control form-control-sm"></div>
          <div class="col-sm-4"><input type="text" name="contact_state" value="{{ old('contact_state') }}" placeholder="Provinsi" class="form-control form-control-sm"></div>
          <div class="col-sm-4"><input type="text" name="contact_postal_code" value="{{ old('contact_postal_code') }}" placeholder="Kode Pos" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2">
          <div class="col-sm-4"><input type="text" name="contact_country" value="{{ old('contact_country', 'ID') }}" maxlength="2" placeholder="Kode Negara (ID)" class="form-control form-control-sm"></div>
          <div class="col-sm-4"><input type="text" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="+62.8123456789" class="form-control form-control-sm"></div>
          <div class="col-sm-4"><input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="email@contoh.com" class="form-control form-control-sm"></div>
        </div>
      </div>
    @endunless

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.domains') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
