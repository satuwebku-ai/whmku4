@extends('layouts.admin')

@section('title', $group->exists ? 'Edit Grup Server' : 'Tambah Grup Server')

@section('content')

  @php $selectStyle = 'padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem'; @endphp

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $group->exists ? 'Edit Grup Server' : 'Tambah Grup Server' }}</h1>
    <p class="small text-muted mb-0">Pilih server cPanel untuk provisioning otomatis. Grup ini kemudian dapat dipilih di halaman Produk.</p>
  </div>

  <form method="POST" action="{{ $group->exists ? route('admin.server-groups.update', $group) : route('admin.server-groups.store') }}" class="card border rounded-4 p-4" style="max-width:52rem" autocomplete="off">
    @csrf
    @if ($group->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-7">
        <label class="form-label small fw-medium text-dark">Nama Grup</label>
        <input type="text" name="name" value="{{ old('name', $group->name) }}" placeholder="Shared Hosting" class="form-control form-control-sm" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-5">
        <label class="form-label small fw-medium text-dark">Slug (opsional)</label>
        <input type="text" name="slug" value="{{ old('slug', $group->slug) }}" placeholder="otomatis dari nama" class="form-control form-control-sm">
        @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Mode Pemilihan Server</label>
      <select name="selection_mode" class="form-select" style="{{ $selectStyle }}">
        @foreach (\App\Models\ServerGroup::MODES as $key => $label)
          <option value="{{ $key }}" @selected(old('selection_mode', $group->selection_mode) === $key)>{{ $label }}</option>
        @endforeach
      </select>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Server yang penuh (Kapasitas Maks. Akun), nonaktif, atau sedang maintenance selalu dilewati, apa pun modenya.
      </p>
      @error('selection_mode') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Deskripsi (opsional)</label>
      <textarea name="description" rows="2" class="form-control form-control-sm">{{ old('description', $group->description) }}</textarea>
      @error('description') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    {{-- Server anggota: dicentang langsung di sini, prioritas per grup. --}}
    @php
      $checkedIds = old('servers') !== null
          ? array_map('intval', (array) old('servers'))
          : array_keys($members ?? []);
    @endphp
    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Server Anggota</label>
      @if (($servers ?? collect())->isEmpty())
        <p class="small text-muted mb-0">Belum ada server hosting. <a href="{{ route('admin.servers.create') }}" target="_blank">Tambah server dulu →</a></p>
      @else
        <div class="border rounded-3 overflow-hidden">
          <table class="table table-sm align-middle mb-0" style="font-size:13px">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="px-3 py-2" style="width:36px"></th>
                <th class="py-2">Server</th>
                <th class="py-2">Panel</th>
                <th class="text-center py-2">Akun</th>
                <th class="py-2">Status</th>
                <th class="py-2" style="width:96px">Prioritas</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($servers as $srv)
                @php
                  $isMember = in_array($srv->id, $checkedIds, true);
                  $full = $srv->max_accounts !== null && $srv->active_accounts_count >= $srv->max_accounts;
                  $prio = old('priority.' . $srv->id, $members[$srv->id]['priority'] ?? ($srv->priority ?? 10));
                @endphp
                <tr>
                  <td class="px-3"><input type="checkbox" name="servers[]" value="{{ $srv->id }}" class="form-check-input" @checked($isMember)></td>
                  <td class="fw-medium text-dark">{{ $srv->name }}<br><span class="text-muted fw-normal" style="font-size:11px">{{ $srv->hostname }}</span></td>
                  <td class="text-muted">
                    {{ strtoupper($srv->panel) }}
                    @if ($srv->panel !== 'cpanel' || filled($srv->vps_provider))
                      <span class="badge badge-soft-warning ms-1">Tidak dipakai untuk order otomatis</span>
                    @endif
                  </td>
                  <td class="text-center text-muted">{{ $srv->active_accounts_count }}@if ($srv->max_accounts !== null) / {{ $srv->max_accounts }}@endif</td>
                  <td>
                    @if (! $srv->is_active) <span class="badge badge-soft-secondary">Nonaktif</span>
                    @elseif ($srv->is_maintenance) <span class="badge badge-soft-warning">Maintenance</span>
                    @elseif ($full) <span class="badge badge-soft-danger">Penuh</span>
                    @else <span class="badge badge-soft-success">Siap</span> @endif
                  </td>
                  <td><input type="number" min="1" max="999" name="priority[{{ $srv->id }}]" value="{{ $prio }}" class="form-control form-control-sm"></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="text-muted mt-1 mb-0" style="font-size:11px">
          Prioritas: angka kecil didahulukan (dipakai mode Prioritas, dan sebagai pemutus seri di mode lain).
          Angka prioritas kecil didahulukan. Server non-cPanel lama tetap ditampilkan agar dapat dikeluarkan, tetapi tidak dipilih untuk order otomatis.
        </p>
        @error('servers.*') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        @error('priority.*') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      @endif
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Aktif
    </label>

    <div class="d-flex align-items-center gap-2 pt-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.server-groups.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
