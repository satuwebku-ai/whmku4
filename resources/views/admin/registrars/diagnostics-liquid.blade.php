@extends('layouts.admin')

@section('title', 'Diagnosa Registrar — ' . $registrar->name)

@section('content')

  <a href="{{ route('admin.registrars.index') }}" class="text-decoration-none text-muted" style="font-size:12px">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Registrar
  </a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-2">Diagnosa — {{ $registrar->name }}</h1>
  <p class="small text-muted mb-4">
    Data langsung dari API Liqu.id — cuma membaca, tidak mengubah apa pun di akunmu.
  </p>

  @if (! empty($apiErrors))
    @foreach ($apiErrors as $err)
      <div class="card border rounded-3 p-3 mb-3" style="border-color:#fecaca!important;background:#fef2f2">
        <p class="mb-0" style="font-size:14px;color:#991b1b"><i class="fa-solid fa-circle-exclamation"></i> {{ $err }}</p>
      </div>
    @endforeach
  @endif

  <div class="row g-3">

    {{-- Mata uang akun — Liqu.id konsisten pakai USD, walau dashboard
         mereka kadang menulis "Customer Price" seolah Rupiah. --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100" style="{{ $details && $details['selling_currency'] ? 'border-color:#a7f3d0!important;background:rgba(16,185,129,.04)' : '' }}">
        <h2 class="small fw-bold text-dark mb-3">Mata Uang Akun</h2>
        @if ($details && $details['selling_currency'])
          <p class="fw-bold text-dark mb-2" style="font-size:1.75rem">{{ $details['selling_currency'] }}</p>
          <p class="text-muted mb-0" style="font-size:12px">
            Semua angka harga &amp; saldo dari API ini dalam satuan <b class="text-dark">{{ $details['selling_currency'] }}</b>.
            @if ($details['selling_currency'] === 'USD')
              <br>Artinya kolom "Customer Price" di dashboard Liqu.id juga dalam USD — bukan ribuan Rupiah,
              meski labelnya tertulis begitu.
            @endif
          </p>
        @else
          <p class="text-muted mb-0" style="font-size:14px">Tidak bisa diambil — lihat pesan galat di atas.</p>
        @endif

        @if ($details && ($details['name'] || $details['company']))
          <div class="mt-3 pt-3 border-top text-muted" style="font-size:12px">
            @if ($details['name']) <p class="mb-1">Nama: {{ $details['name'] }}</p> @endif
            @if ($details['company']) <p class="mb-0">Perusahaan: {{ $details['company'] }}</p> @endif
          </div>
        @endif
      </div>
    </div>

    {{-- Saldo --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3">Saldo Deposit</h2>
        @if ($balance)
          <p class="fw-bold mb-2 {{ $balance['balance'] < 20 ? 'text-danger' : 'text-dark' }}" style="font-size:1.75rem">
            {{ $details['selling_currency'] ?? 'USD' }} {{ number_format($balance['balance'], 2) }}
          </p>
          @if ($balance['balance'] < 20)
            <p class="text-danger mb-0" style="font-size:12px">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Saldo tipis — pastikan deposit cukup sebelum ada klien yang membeli,
              karena registrasi domain dipotong langsung dari saldo ini.
            </p>
          @endif
        @else
          <p class="text-muted mb-0" style="font-size:14px">Tidak bisa diambil — lihat pesan galat di atas.</p>
        @endif
      </div>
    </div>
  </div>

  {{-- Contoh format harga mentah --}}
  <div class="card border rounded-4 p-4 mt-3">
    <h2 class="small fw-bold text-dark mb-1">Contoh Format Harga (data mentah)</h2>
    <p class="text-muted mb-3" style="font-size:12px">
      Tiga baris pertama dari daftar harga akunmu — untuk memastikan format angka yang sebenarnya
      dikembalikan API, bukan tebakan.
    </p>

    @if (isset($priceStats))
      <div class="row g-2 mb-3">
        <div class="col-4">
          <div class="rounded-3 border p-2 text-center">
            <p class="fw-bold text-dark mb-0">{{ $priceStats['total'] }}</p>
            <p class="text-muted mb-0" style="font-size:10px">Total baris dari API</p>
          </div>
        </div>
        <div class="col-4">
          <div class="rounded-3 border p-2 text-center">
            <p class="fw-bold text-dark mb-0">{{ $priceStats['premium'] }}</p>
            <p class="text-muted mb-0" style="font-size:10px">Baris premium (dilewati)</p>
          </div>
        </div>
        <div class="col-4">
          <div class="rounded-3 border p-2 text-center" style="background:rgba(16,185,129,.06)">
            <p class="fw-bold mb-0" style="color:#047857">{{ $priceStats['unique'] }}</p>
            <p class="text-muted mb-0" style="font-size:10px">Ekstensi unik (yang disinkron)</p>
          </div>
        </div>
      </div>
      <p class="text-muted mb-3" style="font-size:11px">
        Angka "Ekstensi unik" inilah jumlah TLD yang akan masuk saat Sinkron TLD —
        kalau jauh lebih sedikit dari harapan, berarti akun reseller-mu di Liqu.id
        memang baru diberi akses sebanyak itu (bukan masalah di sistem ini).
      </p>
    @endif

    <pre class="rounded-3 p-3 mb-0" style="background:#1e293b;color:#f1f5f9;font-size:12px;overflow-x:auto">{{ json_encode($priceSample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
  </div>

  {{-- Daftar customer -- Liqu.id SELALU punya endpoint ini, jadi
       ditampilkan tanpa syarat (beda dari file generic yang harus
       ngecek $supportsCustomers, karena tidak semua provider punya). --}}
  <div class="card border rounded-4 overflow-hidden mt-3">
    <div class="px-4 py-3 border-bottom">
      <h2 class="small fw-bold text-dark mb-0">Customer Terbaru di Liqu.id</h2>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">20 customer terakhir yang tercatat di akun ini — termasuk yang dibuat otomatis saat ada klien mendaftarkan domain.</p>
    </div>
    <div>
      @forelse ($customers as $c)
        <div class="px-4 py-3 border-bottom">
          <p class="text-dark mb-0" style="font-size:14px">{{ $c['name'] ?: '(tanpa nama)' }} <span class="text-muted" style="font-size:11px">— ID {{ $c['id'] }}</span></p>
          <p class="text-muted mb-0" style="font-size:11px">{{ $c['email'] ?: '—' }} @if ($c['company']) · {{ $c['company'] }} @endif</p>
        </div>
      @empty
        <p class="text-center text-muted py-4 mb-0" style="font-size:14px">Belum ada customer tercatat, atau gagal diambil — lihat pesan galat di atas.</p>
      @endforelse
    </div>
  </div>

@endsection
