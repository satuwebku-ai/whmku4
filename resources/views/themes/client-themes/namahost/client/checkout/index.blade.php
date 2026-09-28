@extends('client.layout')
@section('title', 'Checkout')

@section('content')

  <a href="{{ route('cart.index') }}" class="text-decoration-none text-muted" style="font-size:12px">&larr; Kembali ke Keranjang</a>

  <h1 class="h4 fw-bold text-dark mt-2 mb-4">Konfirmasi Pesanan</h1>

  @if ($issues)
    <div class="card-public p-4 mb-4" style="border-color:#fecaca!important;background:#fef2f2">
      <p class="fw-semibold mb-2" style="font-size:14px;color:#b91c1c"><i class="fa-solid fa-circle-exclamation"></i> Ada item yang belum bisa diproses:</p>
      <ul class="mb-0 ps-4" style="font-size:14px;color:#dc2626">
        @foreach ($issues as $issue)
          <li style="margin-bottom:.25rem">{{ $issue }}</li>
        @endforeach
      </ul>
      <a href="{{ route('cart.index') }}" class="btn btn-outline-danger btn-sm mt-3">Perbaiki di Keranjang</a>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-lg-8 d-flex flex-column gap-4">

      {{-- Ringkasan item --}}
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Ringkasan Pesanan</h2>
        <div>
          @foreach ($items as $item)
            <div class="py-3 border-bottom d-flex align-items-start justify-content-between gap-3">
              <div>
                @if ($item['type'] === 'product')
                  <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $item['name'] }}</p>
                  <p class="text-muted mb-0" style="font-size:11px">{{ optional(\App\Models\Product::find($item['product_id'] ?? null))->cycleLabel($item['billing_cycle']) ?? (\App\Models\Product::CYCLES[$item['billing_cycle']] ?? $item['billing_cycle']) }}</p>
                  @if (!empty($item['domain_name']))
                    <p class="text-muted mt-1 mb-0" style="font-size:11px">
                      <i class="fa-solid fa-globe" style="font-size:10px"></i>
                      {{ $item['domain_mode'] === 'register' ? 'Daftar domain baru' : 'Pakai domain sendiri' }}: {{ $item['domain_name'] }}
                    </p>
                  @endif
                  @if (!empty($item['selected_options']))
                    <ul class="list-unstyled mt-1 mb-0 d-flex flex-column gap-1">
                      @foreach ($item['selected_options'] as $opt)
                        <li class="text-muted d-flex align-items-center gap-1" style="font-size:11px">
                          <i class="fa-solid fa-plus" style="font-size:9px"></i> {{ $opt['name'] }}
                          <span class="text-dark">— Rp {{ number_format($opt['price'], 0, ',', '.') }}</span>
                        </li>
                      @endforeach
                    </ul>
                  @endif
                @else
                  <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $item['domain_name'] }}</p>
                  <p class="text-muted mb-0" style="font-size:11px">Registrasi domain — {{ $item['years'] }} tahun</p>
                @endif
              </div>
              <p class="fw-semibold text-dark mb-0 flex-shrink-0" style="font-size:14px">Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
            </div>
          @endforeach
        </div>
        <div class="pt-3 mt-2 border-top d-flex flex-column gap-2">
          <div class="d-flex justify-content-between text-muted" style="font-size:14px">
            <span>Subtotal</span>
            <span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
          </div>
          @if ($coupon)
            <div class="d-flex justify-content-between text-success" style="font-size:14px">
              <span>Kupon {{ $coupon->code }} ({{ $coupon->value_label }})</span>
              <span>- Rp {{ number_format($discount, 0, ',', '.') }}</span>
            </div>
          @endif
          <div class="d-flex justify-content-between fw-bold text-dark pt-1" style="font-size:1rem">
            <span>Total</span>
            <span>Rp {{ number_format($subtotal - $discount, 0, ',', '.') }}</span>
          </div>
        </div>
      </div>

      {{-- Data penagihan --}}
      <div class="card-public p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="small fw-bold text-dark mb-0">Data Penagihan</h2>
          <a href="{{ route('client.profile') }}" class="text-decoration-none text-theme" style="font-size:12px">Edit Profil</a>
        </div>
        <div class="row g-3">
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Nama</p><p class="text-dark mb-0" style="font-size:14px">{{ $client->name }}</p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Email</p><p class="text-dark mb-0" style="font-size:14px">{{ $client->email }}</p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Telepon</p><p class="text-dark mb-0" style="font-size:14px">{{ $client->phone ?: '—' }}</p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Alamat</p><p class="text-dark mb-0" style="font-size:14px">{{ $client->address ?: '—' }}, {{ $client->city }}</p></div>
        </div>

        @if ($items->contains(fn ($i) => ($i['type'] ?? null) === 'domain' || ($i['domain_mode'] ?? null) === 'register'))
          @if (blank($client->state) || blank($client->postal_code))
            <div class="mt-3 rounded-3 px-3 py-2" style="background:#fffbeb;border:1px solid #fde68a;font-size:12px;color:#92400e">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Ada pendaftaran domain di pesanan ini. Provinsi & kode pos Anda belum lengkap — data ini wajib untuk registrasi
              domain (WHOIS). Lengkapi dulu di <a href="{{ route('client.profile') }}" class="text-decoration-underline fw-medium" style="color:inherit">halaman Profil</a>
              supaya domain bisa diproses otomatis setelah pembayaran.
            </div>
          @endif
        @endif
      </div>
    </div>

    <div class="col-12 col-lg-4 d-flex flex-column gap-4">
      {{-- Kupon --}}
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Kode Kupon</h2>

        @if ($coupon)
          <div class="d-flex align-items-center justify-content-between rounded-3 px-3 py-2" style="background:#f0fdf4;border:1px solid #a7f3d0">
            <div>
              <p class="fw-semibold mb-0" style="font-size:14px;color:#047857">{{ $coupon->code }}</p>
              <p class="mb-0" style="font-size:11px;color:#059669">Potongan {{ $coupon->value_label }} diterapkan</p>
              @if ($coupon->applies_to === 'specific')
                <p class="mt-1 mb-0" style="font-size:10px;color:#10b981">
                  Hanya berlaku untuk produk tertentu di keranjang — item lain (mis. registrasi domain) tidak ikut didiskon.
                </p>
              @endif
            </div>
            <form method="POST" action="{{ route('client.checkout.coupon.remove') }}">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-link p-0 text-success" style="font-size:12px;text-decoration:underline">Batalkan</button>
            </form>
          </div>
        @else
          <form method="POST" action="{{ route('client.checkout.coupon') }}" class="d-flex gap-2">
            @csrf
            <input type="text" name="code" placeholder="Masukkan kode kupon" class="form-control text-uppercase">
            <button type="submit" class="btn btn-outline-secondary flex-shrink-0">Pakai</button>
          </form>
        @endif
      </div>

      <div class="card-public p-4" style="position:sticky;top:6rem">
        <h2 class="small fw-bold text-dark mb-1">Selesaikan Pesanan</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Setelah dikonfirmasi, invoice akan dibuat dan Anda diarahkan ke halaman pembayaran.
          Layanan aktif otomatis begitu invoice lunas.
        </p>

        <form method="POST" action="{{ route('client.checkout.store') }}">
          @csrf
          <button type="submit" class="btn btn-theme w-100" {{ $issues ? 'disabled' : '' }}>
            <i class="fa-solid fa-check" style="font-size:11px"></i> Buat Pesanan
          </button>
        </form>
      </div>
    </div>
  </div>

@endsection
