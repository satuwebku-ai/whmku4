@extends('client.layout')
@section('title', 'Email Forwarding — ' . $domain->domain_name)

@section('content')
  <a href="{{ route('client.domains.show', $domain) }}" class="text-decoration-none text-muted" style="font-size:12px">
    &larr; Kembali ke {{ $domain->domain_name }}
  </a>

  <div class="mt-2 mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Email Forwarding — {{ $domain->domain_name }}</h1>
    <p class="text-muted mb-0">
      Teruskan email yang masuk ke alamat @{{ $domain->domain_name }} ke email lain — tanpa perlu hosting email sendiri.
    </p>
  </div>

  @if ($warning)
    <div class="card-public p-4 mb-4" style="border-color:#fde68a!important;background:#fffbeb">
      <p class="mb-0" style="font-size:14px;color:#92400e"><i class="fa-solid fa-triangle-exclamation"></i> {{ $warning }}</p>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card-public overflow-hidden">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-0">Forwarding Aktif</h2>
        </div>
        <div>
          @forelse ($forwards as $fwd)
            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
              <div class="min-w-0" style="font-size:14px">
                <p class="fw-medium text-dark text-truncate mb-0">{{ $fwd['email'] }}</p>
                <p class="text-muted mb-0" style="font-size:11px">
                  <i class="fa-solid fa-arrow-right" style="font-size:9px"></i> {{ $fwd['forward_to'] }}
                </p>
              </div>
              <form method="POST" action="{{ route('client.domains.email-forwarding.delete', $domain) }}"
                    data-confirm="Hapus forwarding untuk {{ $fwd['email'] }}?" data-confirm-title="Hapus Email Forwarding" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                @csrf @method('DELETE')
                <input type="hidden" name="email" value="{{ $fwd['email'] }}">
                <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;padding:0">
                  <i class="fa-regular fa-trash-can" style="font-size:11px"></i>
                </button>
              </form>
            </div>
          @empty
            <p class="text-center text-muted py-5 mb-0" style="font-size:14px">Belum ada email forwarding.</p>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Tambah Forwarding</h2>
        <form method="POST" action="{{ route('client.domains.email-forwarding.add', $domain) }}" class="d-flex flex-column gap-3">
          @csrf
          <div>
            <label class="form-label">Alamat di Domain Ini</label>
            <div class="d-flex align-items-center gap-2">
              <input type="text" name="email" placeholder="info" class="form-control">
              <span class="text-muted flex-shrink-0" style="font-size:13px">@{{ $domain->domain_name }}</span>
            </div>
            @error('email') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <div>
            <label class="form-label">Teruskan ke Email</label>
            <input type="email" name="forward_to" placeholder="tujuan@gmail.com" class="form-control">
            @error('forward_to') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
          <button type="submit" class="btn btn-theme w-100">
            <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah
          </button>
        </form>
      </div>
    </div>
  </div>
@endsection
