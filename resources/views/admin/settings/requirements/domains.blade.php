@extends('layouts.admin')
@section('title', 'Persyaratan per Domain')
@section('content')
  @include('admin.settings._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Persyaratan per Domain</h1>
      <p class="small text-muted mb-0">
        Cari ekstensi domain, lalu centang berkas apa saja yang harus dipenuhi klien sebelum domain itu bisa diproses.
      </p>
    </div>
    <a href="{{ route('admin.settings.requirements.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-list" style="font-size:11px"></i> Kelola Daftar Berkas
    </a>
  </div>

  @if ($requirements->isEmpty())
    <div class="card border rounded-4 p-5 text-center">
      <i class="fa-solid fa-folder-open text-muted mb-3" style="font-size:1.75rem"></i>
      <p class="fw-medium text-dark mb-1">Belum ada jenis berkas yang bisa dipetakan</p>
      <p class="text-muted mb-3" style="font-size:13px">Tambahkan dulu jenis berkasnya (KTP, NIB, dst), baru bisa dipetakan ke domain.</p>
      <a href="{{ route('admin.settings.requirements.create') }}" class="btn btn-primary btn-sm mx-auto" style="width:fit-content">
        <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Persyaratan
      </a>
    </div>
  @else
    <form method="GET" class="d-flex gap-2 mb-3" style="max-width:26rem">
      <input type="text" name="search" value="{{ $search }}" placeholder="Cari ekstensi, mis. .co.id" class="form-control form-control-sm">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Cari</button>
      @if ($search)
        <a href="{{ route('admin.settings.requirements.domains') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
      @endif
    </form>

    @if (! $search)
      <div class="rounded-3 p-3 mb-3" style="background:#f8fafc;border:1px dashed #cbd5e1">
        <p class="text-muted mb-0" style="font-size:11px">
          <i class="fa-solid fa-circle-info"></i>
          Menampilkan ekstensi yang <b>sudah punya persyaratan</b> saja. Untuk menambahkan ke ekstensi lain,
          cari ekstensinya di kotak pencarian di atas.
        </p>
      </div>
    @endif

    @php
      // Tanpa pencarian, cukup tampilkan yang sudah dipetakan -- daftar
      // penuh bisa ratusan ekstensi dan tidak ada gunanya digulir semua.
      $tampil = $search ? $extensions : $extensions->filter(fn ($e) => ! empty($current[$e] ?? []))->values();
    @endphp

    <div class="d-flex flex-column gap-2">
      @forelse ($tampil as $ext)
        @php $dipilih = $current[$ext] ?? []; @endphp
        <form method="POST" action="{{ route('admin.settings.requirements.domains.update') }}"
              class="card border rounded-4 p-3 {{ $dipilih ? '' : 'bg-white' }}"
              style="{{ $dipilih ? 'border-color:#c7d2fe!important;background:rgba(79,70,229,.03)' : '' }}">
          @csrf
          <input type="hidden" name="extension" value="{{ $ext }}">

          <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <span class="fw-bold text-dark" style="font-family:monospace;font-size:14px">{{ $ext }}</span>
            <span class="text-muted" style="font-size:11px">
              @if ($dipilih)
                {{ count($dipilih) }} berkas diwajibkan
              @else
                Tanpa persyaratan — domain langsung diproses
              @endif
            </span>
          </div>

          <div class="d-flex flex-wrap gap-3 mb-3">
            @foreach ($requirements as $req)
              <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:12px">
                <input type="checkbox" name="requirements[]" value="{{ $req->id }}"
                       @checked(in_array($req->id, $dipilih, true)) class="form-check-input" style="margin-top:0">
                {{ $req->name }}
                @unless ($req->is_required)
                  <span class="badge badge-soft-secondary" style="font-size:9px">opsional</span>
                @endunless
              </label>
            @endforeach
          </div>

          <button type="submit" class="btn btn-primary btn-sm" style="width:fit-content">
            <i class="fa-solid fa-check" style="font-size:11px"></i> Simpan {{ $ext }}
          </button>
        </form>
      @empty
        <div class="card border rounded-4 p-5 text-center">
          <p class="text-muted mb-0" style="font-size:14px">
            @if ($search)
              Tidak ada ekstensi yang cocok dengan "{{ $search }}".
            @else
              Belum ada domain yang dipetakan. Cari ekstensinya di atas untuk mulai menambahkan.
            @endif
          </p>
        </div>
      @endforelse
    </div>
  @endif
@endsection
