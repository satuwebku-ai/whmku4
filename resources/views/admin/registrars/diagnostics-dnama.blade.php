@extends('layouts.admin')

@section('title', 'Diagnosa Registrar — ' . $registrar->name)

@section('content')

  <a href="{{ route('admin.registrars.index') }}" class="text-decoration-none text-muted" style="font-size:12px">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Registrar
  </a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-2">Diagnosa — {{ $registrar->name }}</h1>
  <p class="small text-muted mb-4">
    Data langsung dari API DNAMA — cuma membaca, tidak mengubah apa pun di akunmu.
  </p>

  @if (! empty($apiErrors))
    @foreach ($apiErrors as $err)
      <div class="card border rounded-3 p-3 mb-3" style="border-color:#fecaca!important;background:#fef2f2">
        <p class="mb-0" style="font-size:14px;color:#991b1b"><i class="fa-solid fa-circle-exclamation"></i> {{ $err }}</p>
      </div>
    @endforeach
  @endif

  <div class="row g-3">

    {{-- DNAMA selalu IDR, tapi kartu ini dipertahankan sama seperti
         provider lain supaya kalau ada perubahan di masa depan tetap
         konsisten polanya. --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100" style="{{ $details && $details['selling_currency'] ? 'border-color:#a7f3d0!important;background:rgba(16,185,129,.04)' : '' }}">
        <h2 class="small fw-bold text-dark mb-3">Mata Uang Akun</h2>
        @if ($details && $details['selling_currency'])
          <p class="fw-bold text-dark mb-2" style="font-size:1.75rem">{{ $details['selling_currency'] }}</p>
          <p class="text-muted mb-0" style="font-size:12px">
            Semua angka harga &amp; saldo dari API DNAMA dalam satuan <b class="text-dark">{{ $details['selling_currency'] }}</b> --
            tidak perlu dikonversi, langsung Rupiah.
          </p>
        @else
          <p class="fw-bold text-dark mb-2" style="font-size:1.75rem">IDR</p>
          <p class="text-muted mb-0" style="font-size:12px">DNAMA selalu memakai Rupiah untuk semua transaksinya.</p>
        @endif
      </div>
    </div>

    {{-- Saldo --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3">Saldo Deposit</h2>
        @if ($balance)
          <p class="fw-bold mb-2 {{ $balance['balance'] < 50000 ? 'text-danger' : 'text-dark' }}" style="font-size:1.75rem">
            Rp {{ number_format($balance['balance'], 0, ',', '.') }}
          </p>
          @if ($balance['balance'] < 50000)
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

  {{-- Statistik & contoh harga mentah -- lebih ditekankan di sini
       dibanding file generic, karena DNAMA punya kuirk khusus: satu
       ekstensi bisa muncul BERKALI-KALI (varian premium 2/3/4-karakter
       dengan harga sampai ratusan juta), jadi statistik ini penting
       untuk memastikan filternya bekerja benar. --}}
  <div class="card border rounded-4 p-4 mt-3">
    <h2 class="small fw-bold text-dark mb-1">Statistik & Contoh Harga (data mentah)</h2>
    <p class="text-muted mb-3" style="font-size:12px">
      DNAMA mengirim baris TERPISAH untuk varian premium dari ekstensi yang sama
      (mis. ".id" biasa Rp 215rb DAN ".id" premium 2-karakter Rp 585 juta, sama-sama
      bertuliskan "tld": ".id"). Baris premium otomatis dilewati saat sinkronisasi --
      angka di bawah ini menunjukkan persis berapa yang dilewati dan berapa yang benar-benar disinkron.
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
          <div class="rounded-3 border p-2 text-center" style="background:rgba(180,83,9,.06)">
            <p class="fw-bold mb-0" style="color:#b45309">{{ $priceStats['premium'] }}</p>
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
        kalau jauh lebih sedikit dari harapan, berarti akun reseller-mu di DNAMA
        memang baru diberi akses sebanyak itu (bukan masalah di sistem ini).
      </p>
    @endif

    {{-- Tabel terbaca dulu, JSON mentah disembunyikan di bawahnya --
         JSON penuh untuk 38 baris x 10 durasi terlalu panjang untuk
         diperiksa mata, padahal yang biasanya mau dicek cuma "harga
         .id berapa" dan "kenapa TLD ini tidak masuk". --}}
    @php
      $parsed = collect(is_array($priceSample) ? $priceSample : [])->map(function ($row) {
        $satu = collect($row['pricings'] ?? [])->firstWhere('duration', 1);
        return [
          'tld' => $row['tld'] ?? '—',
          'premium' => ! empty($row['is_premium']),
          'chars' => $row['max_premium_character'] ?? null,
          'register' => $satu['register_price'] ?? null,
          'renew' => $satu['renewal_price'] ?? null,
          'transfer' => $satu['transfer_price'] ?? null,
          'restore' => $satu['restore_price'] ?? null,
          'durasi' => count($row['pricings'] ?? []),
        ];
      });
    @endphp

    @if ($parsed->isNotEmpty())
      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0" style="font-size:12px">
          <thead>
            <tr class="text-uppercase text-muted" style="background:#f8fafc;font-size:10px">
              <th class="py-2">TLD</th>
              <th class="text-end py-2">Register (1thn)</th>
              <th class="text-end py-2">Renew</th>
              <th class="text-end py-2">Transfer</th>
              <th class="text-end py-2">Restore</th>
              <th class="text-center py-2">Durasi</th>
              <th class="py-2">Catatan</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($parsed as $row)
              <tr style="{{ $row['premium'] ? 'background:rgba(180,83,9,.05)' : '' }}">
                <td class="py-2 fw-medium text-dark">{{ $row['tld'] }}</td>
                <td class="text-end py-2">{{ $row['register'] !== null ? 'Rp ' . number_format($row['register'], 0, ',', '.') : '—' }}</td>
                <td class="text-end py-2">{{ $row['renew'] !== null ? 'Rp ' . number_format($row['renew'], 0, ',', '.') : '—' }}</td>
                <td class="text-end py-2">{{ $row['transfer'] !== null ? 'Rp ' . number_format($row['transfer'], 0, ',', '.') : '—' }}</td>
                <td class="text-end py-2 text-muted">{{ $row['restore'] !== null ? 'Rp ' . number_format($row['restore'], 0, ',', '.') : '—' }}</td>
                <td class="text-center py-2 text-muted">{{ $row['durasi'] }}</td>
                <td class="py-2">
                  @if ($row['premium'])
                    <span class="badge" style="font-size:9px;background:#fef3c7;color:#b45309">
                      Premium{{ $row['chars'] ? ' ' . $row['chars'] . ' karakter' : '' }} — dilewati
                    </span>
                  @else
                    <span class="text-muted" style="font-size:10px">disinkron</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="text-muted mb-2" style="font-size:11px">
        Ini 3 baris pertama saja. Baris premium (latar oranye) sengaja dilewati saat sinkronisasi —
        harganya bisa ratusan juta dan cuma berlaku untuk domain super-pendek.
      </p>
    @endif

    <details>
      <summary class="text-muted" style="font-size:11px;cursor:pointer">Lihat JSON mentah</summary>
      <pre class="rounded-3 p-3 mt-2 mb-0" style="background:#1e293b;color:#f1f5f9;font-size:11px;overflow-x:auto;max-height:24rem">{{ json_encode($priceSample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
  </div>

  {{-- DNAMA punya GET /customers/{username} (cari satu customer), tapi
       TIDAK punya endpoint untuk MENDAFTAR semua customer sekaligus --
       jadi di sini disediakan kotak pencarian, bukan daftar seperti
       di halaman diagnosa Liqu.id. --}}
  @if ($supportsCustomerLookup ?? false)
    <div class="card border rounded-4 p-4 mt-3">
      <h2 class="small fw-bold text-dark mb-1">Cari Customer</h2>
      <p class="text-muted mb-3" style="font-size:12px">
        DNAMA tidak menyediakan endpoint "daftar semua customer", tapi customer bisa dicari
        satu per satu lewat username-nya (biasanya alamat email klien).
      </p>

      <form method="GET" class="d-flex gap-2 mb-3" style="max-width:28rem">
        <input type="text" name="customer" value="{{ $customerLookup['query'] ?? '' }}"
               placeholder="username / email customer" class="form-control form-control-sm">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Cari</button>
      </form>

      @if ($customerLookup)
        @if ($customerLookup['found'] && $customerLookup['data'])
          @php $c = $customerLookup['data']; @endphp
          <div class="rounded-3 p-3" style="background:rgba(16,185,129,.05);border:1px solid #a7f3d0">
            <p class="fw-medium text-dark mb-1" style="font-size:14px">{{ $c['name'] ?? '(tanpa nama)' }}</p>
            <p class="text-muted mb-2" style="font-size:12px">
              {{ $c['email'] ?? '—' }}
              @if (! empty($c['company_name'])) &middot; {{ $c['company_name'] }} @endif
            </p>
            <div class="text-muted" style="font-size:11px">
              @if (! empty($c['address_1']))
                <p class="mb-0">{{ collect([$c['address_1'] ?? null, $c['address_2'] ?? null, $c['address_3'] ?? null])->filter()->implode(', ') }}</p>
              @endif
              <p class="mb-0">
                {{ collect([$c['city'] ?? null, $c['province'] ?? null, $c['postal_code'] ?? null, $c['country'] ?? null])->filter()->implode(' · ') }}
              </p>
              @if (! empty($c['phone_number']))
                <p class="mb-0">Telp: {{ $c['phone_number'] }}@if (! empty($c['mobile_phone_number']) && $c['mobile_phone_number'] !== $c['phone_number']) &middot; HP: {{ $c['mobile_phone_number'] }} @endif</p>
              @endif
            </div>
          </div>
        @else
          <div class="rounded-3 p-3" style="background:#fef2f2;border:1px solid #fecaca">
            <p class="mb-0" style="font-size:13px;color:#991b1b">
              <i class="fa-solid fa-circle-exclamation"></i>
              Customer "{{ $customerLookup['query'] }}" tidak ditemukan.
              @if ($customerLookup['message'])
                <span class="d-block text-muted mt-1" style="font-size:11px">{{ $customerLookup['message'] }}</span>
              @endif
            </p>
          </div>
        @endif
      @endif
    </div>
  @endif

@endsection
