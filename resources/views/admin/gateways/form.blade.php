@extends('layouts.admin')

@section('title', $gateway->exists ? 'Edit Gateway' : 'Tambah Gateway')

@section('content')

  {{-- Tab atas: Transaksi vs Gateway --}}
  <div class="d-flex align-items-center gap-1 mb-3 border-bottom flex-wrap">
    @php
      $topTabs = [
        ['label' => 'Transaksi', 'route' => 'admin.payments'],
        ['label' => 'Gateway', 'route' => 'admin.gateways'],
      ];
    @endphp
    @foreach ($topTabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route']) . '*') ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $gateway->exists ? 'Edit Payment Gateway' : 'Tambah Payment Gateway' }}</h1>
    <p class="small text-muted mb-0">Semua kredensial dienkripsi otomatis di database.</p>
  </div>

  @php
    $hintStyle = 'background:#eef2ff;border:1px solid #c7d2fe;color:#4338ca';
    $hintStyleWarn = 'background:#fffbeb;border:1px solid #fde68a;color:#92400e';
  @endphp

  <div id="hint-midtrans" class="driver-hint d-none rounded-3 px-3 py-2 small mb-3" style="max-width:42rem;{{ $hintStyle }}">
    <i class="fa-solid fa-circle-info"></i>
    <b>Midtrans:</b> ambil Server Key &amp; Client Key dari Dashboard Midtrans » Settings » Access Keys.
    Daftarkan Payment Notification URL berikut di Settings » Configuration:
    <code class="px-1 rounded" style="background:rgba(255,255,255,.6)">{{ url('/payment/webhook/midtrans') }}</code>
  </div>

  <div id="hint-xendit" class="driver-hint d-none rounded-3 px-3 py-2 small mb-3" style="max-width:42rem;{{ $hintStyle }}">
    <i class="fa-solid fa-circle-info"></i>
    <b>Xendit:</b> isi Secret API Key di field Server Key, dan Callback Verification Token di field Callback Token
    (Dashboard Xendit » Settings » Developers). Daftarkan webhook URL:
    <code class="px-1 rounded" style="background:rgba(255,255,255,.6)">{{ url('/payment/webhook/xendit') }}</code>
  </div>

  <div id="hint-duitku" class="driver-hint d-none rounded-3 px-3 py-2 small mb-3" style="max-width:42rem;{{ $hintStyle }}">
    <i class="fa-solid fa-circle-info"></i>
    <b>Duitku:</b> isi Merchant Code di field Client Key, dan API Key di field Server Key
    (Dashboard Duitku » Pengaturan » Konfigurasi API). Daftarkan Callback URL berikut di sana:
    <code class="px-1 rounded" style="background:rgba(255,255,255,.6)">{{ url('/payment/webhook/duitku') }}</code>
    <br>Mode Sandbox memakai kredensial project uji coba Duitku — biasanya perlu didaftarkan terpisah dari akun production.
    <br><br>
    <b>Kode Metode QRIS (opsional):</b> isi kolom "Kode Metode QRIS" di bawah kalau ingin kode QR
    tampil langsung di halaman invoice (tanpa klien diarahkan ke situs Duitku). Kodenya berbeda tiap akun —
    lihat di Dashboard Duitku » Metode Pembayaran, cari baris QRIS dan salin kodenya persis.
    Dikosongkan = QRIS tetap bisa dipakai lewat halaman Duitku biasa (redirect), fitur tertanam saja yang nonaktif.
  </div>

  <div id="hint-manual" class="driver-hint d-none rounded-3 px-3 py-2 small mb-3" style="max-width:42rem;{{ $hintStyleWarn }}">
    <i class="fa-solid fa-circle-info"></i>
    <b>Transfer Manual:</b> tidak memanggil API apapun. Isi instruksi transfer (nomor rekening, nama penerima)
    di kolom Instruksi. Pembayaran diverifikasi admin lewat tombol Setujui di halaman detail.
  </div>

  <form method="POST" action="{{ $gateway->exists ? route('admin.gateway.update', $gateway) : route('admin.gateway.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf
    @if ($gateway->exists) @method('PUT') @endif

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Nama (dilihat klien)</label>
        <input type="text" name="name" value="{{ old('name', $gateway->name) }}" placeholder="Transfer Bank BCA" class="form-control form-control-sm" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Driver</label>
        <select name="driver" id="driverSelect" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
          @foreach ($drivers as $key => $label)
            <option value="{{ $key }}" @selected(old('driver', $gateway->driver ?? 'midtrans') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div id="fieldsAuto">
      <div id="fieldMode" class="mb-3">
        <label class="form-label small fw-medium text-dark">Mode</label>
        <select name="mode" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem">
          <option value="sandbox" @selected(old('mode', $gateway->mode ?? 'sandbox') === 'sandbox')>Sandbox (testing)</option>
          <option value="production" @selected(old('mode', $gateway->mode) === 'production')>Production</option>
        </select>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark"><span id="labelServerKey">Server Key</span> {{ $gateway->exists ? '(kosongkan jika tidak diganti)' : '' }}</label>
          <input type="password" name="server_key" placeholder="{{ $gateway->exists ? '••••••••••••' : '' }}" class="form-control form-control-sm">
          @error('server_key') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>
        <div id="fieldClientKey" class="col-sm-6">
          <label class="form-label small fw-medium text-dark"><span id="labelClient">Client Key</span> {{ $gateway->exists ? '(opsional)' : '' }}</label>
          <input type="password" name="client_key" placeholder="{{ $gateway->exists ? '••••••••••••' : '' }}" class="form-control form-control-sm">
        </div>
      </div>

      <div id="fieldCallbackToken" class="mb-3">
        <label class="form-label small fw-medium text-dark">Callback Verification Token {{ $gateway->exists ? '(kosongkan jika tidak diganti)' : '' }}</label>
        <input type="password" name="callback_token" placeholder="{{ $gateway->exists ? '••••••••••••' : '' }}" class="form-control form-control-sm">
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Wajib untuk Xendit — dipakai memverifikasi keaslian webhook.</p>
      </div>

      <div id="fieldQrisCode" class="d-none mb-3">
        <label class="form-label small fw-medium text-dark">Kode Metode QRIS <span class="text-muted fw-normal">(opsional)</span></label>
        <input type="text" name="qris_method_code" value="{{ old('qris_method_code', $gateway->qris_method_code) }}"
               placeholder="mis. SP — lihat Dashboard Duitku » Metode Pembayaran" class="form-control form-control-sm">
        @error('qris_method_code') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">
          Isi supaya kode QR tampil langsung di halaman invoice. Kosongkan kalau tidak yakin —
          QRIS tetap berfungsi lewat halaman Duitku biasa.
        </p>
      </div>
    </div>

    <div id="fieldInstructions" class="d-none mb-3">
      <label class="form-label small fw-medium text-dark">Instruksi Transfer</label>
      <textarea name="instructions" rows="4" class="form-control form-control-sm" placeholder="Bank BCA&#10;No. Rek: 1234567890&#10;a/n PT Contoh Hosting">{{ old('instructions', $gateway->instructions) }}</textarea>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Biaya Tetap (Rp)</label>
        <input type="number" step="0.01" name="fee_flat" value="{{ old('fee_flat', $gateway->fee_flat ?? 0) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Biaya Persentase (%)</label>
        <input type="number" step="0.01" name="fee_percent" value="{{ old('fee_percent', $gateway->fee_percent ?? 0) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-4">
        <label class="form-label small fw-medium text-dark">Mata Uang</label>
        <input type="text" name="currency" maxlength="3" value="{{ old('currency', $gateway->currency ?? 'IDR') }}" class="form-control form-control-sm" required>
      </div>
    </div>

    <div class="row g-3 mb-3 align-items-center">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $gateway->sort_order ?? 0) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-6">
        <label class="d-flex align-items-center gap-2 small text-dark mb-0">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $gateway->is_active ?? true)) class="form-check-input" style="margin-top:0">
          Aktif
        </label>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.gateways') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>

  <script>
    (function () {
      const select        = document.getElementById('driverSelect');
      const fieldsAuto    = document.getElementById('fieldsAuto');
      const fieldClient   = document.getElementById('fieldClientKey');
      const fieldToken    = document.getElementById('fieldCallbackToken');
      const fieldQris     = document.getElementById('fieldQrisCode');
      const fieldInstr    = document.getElementById('fieldInstructions');
      const labelServer   = document.getElementById('labelServerKey');
      const labelClient   = document.getElementById('labelClient');

      function sync() {
        const driver = select.value;
        const isManual = driver === 'manual';

        // Gateway manual tidak butuh kredensial API sama sekali.
        fieldsAuto.classList.toggle('d-none', isManual);
        fieldInstr.classList.toggle('d-none', !isManual);

        // Client Key dipakai Midtrans (Client Key) & Duitku (Merchant Code).
        // Callback Token hanya dipakai Xendit — Duitku memverifikasi lewat
        // signature MD5 yang dihitung dari Merchant Code + API Key, bukan
        // token terpisah.
        fieldClient.classList.toggle('d-none', ! ['midtrans', 'duitku'].includes(driver));
        fieldToken.classList.toggle('d-none', driver !== 'xendit');
        fieldQris.classList.toggle('d-none', driver !== 'duitku');

        labelServer.textContent = driver === 'xendit' ? 'Secret API Key' : (driver === 'duitku' ? 'API Key' : 'Server Key');
        labelClient.textContent = driver === 'duitku' ? 'Merchant Code' : 'Client Key';

        document.querySelectorAll('.driver-hint').forEach(el => el.classList.add('d-none'));
        document.getElementById('hint-' + driver)?.classList.remove('d-none');
      }

      select.addEventListener('change', sync);
      sync();
    })();
  </script>

@endsection
