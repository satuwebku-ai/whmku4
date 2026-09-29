@extends('public.layout')

@php
  $seoTitle = 'Domain Premium';
  $seoDescription = 'Cek & pesan domain premium .id — harga tetap berdasarkan jumlah karakter atau domain premium custom, lengkap dengan persyaratan PANDI, bisa langsung dibayar termasuk lewat Transfer Manual.';
  $activeTab = request()->hasAny(['cari', 'urut', 'page']) ? 'custom' : 'karakter';
@endphp

@section('content')

  <div class="mb-4">
    @include('public._promo-banner-carousel')
  </div>

  <div class="mb-4">
    <p class="text-muted mb-2" style="font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase">Domain Premium</p>
    <h1 class="fw-bold text-dark mb-2" style="font-size:1.6rem">Domain Premium .id</h1>
    <p class="text-muted mb-0" style="max-width:44rem">
      Domain premium adalah nama domain bernilai tinggi yang dipatok registry dengan harga khusus,
      berbeda dari harga domain reguler. Harganya tetap berdasarkan jumlah karakter nama — ketik nama
      yang diinginkan, kami cek ketersediaannya sekaligus tampilkan harganya, lalu bisa langsung dipesan
      dan dibayar (termasuk lewat Transfer Manual) seperti domain biasa.
    </p>
  </div>

  <style>
    .pd-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}
    .pd-tab{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem 1rem;border-radius:.6rem;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s}
    .pd-tab:hover{border-color:#9ca3af}
    .pd-tab.is-active{background:var(--bs-primary,#0f766e);border-color:var(--bs-primary,#0f766e);color:#fff}
    .pd-tab .pd-count{font-size:11px;font-weight:600;padding:.05rem .45rem;border-radius:999px;background:rgba(0,0,0,.08)}
    .pd-tab.is-active .pd-count{background:rgba(255,255,255,.25)}
    .pd-panel[hidden]{display:none!important}
    .pd-req td,.pd-req th{vertical-align:top}
  </style>

  {{-- ══════════ Tab: Karakter / Custom ══════════ --}}
  <div class="pd-tabs" role="tablist" aria-label="Jenis domain premium">
    <button type="button" class="pd-tab {{ $activeTab === 'karakter' ? 'is-active' : '' }}" role="tab" id="tabBtnKarakter"
            data-tab="karakter" aria-controls="panelKarakter" aria-selected="{{ $activeTab === 'karakter' ? 'true' : 'false' }}">
      <i class="fa-solid fa-hashtag" style="font-size:12px"></i> Domain Premium Karakter
    </button>
    <button type="button" class="pd-tab {{ $activeTab === 'custom' ? 'is-active' : '' }}" role="tab" id="tabBtnCustom"
            data-tab="custom" aria-controls="panelCustom" aria-selected="{{ $activeTab === 'custom' ? 'true' : 'false' }}">
      <i class="fa-solid fa-gem" style="font-size:12px"></i> Domain Premium Custom
      <span class="pd-count">{{ number_format($customTotal, 0, ',', '.') }}</span>
    </button>
  </div>

  {{-- Panel 1: harga per jumlah karakter (cek nama + tabel referensi harga) --}}
  <div id="panelKarakter" class="pd-panel" role="tabpanel" aria-labelledby="tabBtnKarakter" @if ($activeTab !== 'karakter') hidden @endif>

  {{-- ══════════ Cek & Pesan ══════════ --}}
  <div class="card-public p-4 mb-4">
    <h2 class="h6 fw-bold text-dark mb-3">Cek &amp; Pesan Domain Premium .id</h2>

    @if ($idFamily['error'])
      <div class="rounded-3 px-3 py-2 mb-3" style="background:#fef2f2;border:1px solid #fecaca;font-size:13px;color:#b91c1c">
        Daftar harga sedang tidak bisa dimuat ({{ $idFamily['error'] }}). Silakan coba lagi beberapa saat lagi.
      </div>
    @elseif (empty($idFamily['rows']))
      <p class="text-muted mb-0" style="font-size:13px">Daftar harga belum tersedia saat ini.</p>
    @else
      <form id="premiumOrderForm" class="d-flex flex-column flex-sm-row gap-2" style="max-width:34rem">
        @csrf
        <input type="text" id="premiumLabelInput" placeholder="contoh: toko"
               class="form-control" style="font-size:14px" required autocomplete="off">
        <select id="premiumExtSelect" class="form-select flex-shrink-0" style="max-width:8rem">
          @foreach (array_keys($idFamily['rows']) as $ext)
            <option value="{{ $ext }}">{{ $ext }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-theme flex-shrink-0">
          <i class="fa-solid fa-magnifying-glass" style="font-size:12px"></i> Cek
        </button>
      </form>

      <div id="premiumCheckResult" class="mt-3" style="display:none;font-size:13px"></div>

      <p class="text-muted mt-3 mb-0" style="font-size:12px">
        Harga berlaku untuk registrasi baru 1 tahun dan otomatis disesuaikan dengan jumlah karakter
        nama yang Anda masukkan. Anda perlu masuk/daftar akun sebelum checkout. Dokumen persyaratan
        (KTP/NPWP dst.) sama seperti pendaftaran domain .id biasa, dan akan diminta setelah pesanan
        dibuat, sebelum invoice bisa dibayar.
      </p>
    @endif
  </div>

  {{-- ══════════ Referensi harga per ekstensi ══════════ --}}
  @if (! $idFamily['error'] && ! empty($idFamily['rows']))
    <div class="card-public p-4 mb-4">
      <h2 class="h6 fw-bold text-dark mb-3">Referensi Harga</h2>

      <div style="overflow-x:auto">
        <table class="table align-middle mb-0" style="font-size:13px">
          <thead>
            <tr class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em">
              <th>Ekstensi</th>
              <th class="text-end">Registrasi / tahun</th>
              <th class="text-end">Perpanjangan / tahun</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($idFamily['rows'] as $ext => $variants)
              @foreach ($variants as $row)
                <tr>
                  <td class="fw-semibold text-dark">
                    {{ $row['label'] }}
                    @if ($row['is_premium'])
                      <span class="badge ms-1" style="background:#fef3c7;color:#92400e;font-weight:600;font-size:10px">Premium</span>
                    @endif
                  </td>
                  <td class="text-end">
                    {{ $row['register'] !== null ? 'Rp ' . number_format($row['register'], 0, ',', '.') : '—' }}
                  </td>
                  <td class="text-end">
                    {{ $row['renew'] !== null ? 'Rp ' . number_format($row['renew'], 0, ',', '.') : '—' }}
                  </td>
                </tr>
              @endforeach
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  </div>{{-- /panelKarakter --}}

  {{-- Panel 2: domain premium custom (tabel harga per nama) --}}
  <div id="panelCustom" class="pd-panel" role="tabpanel" aria-labelledby="tabBtnCustom" @if ($activeTab !== 'custom') hidden @endif>
    @include('public.catalog._custom-premium')
  </div>

  {{-- ══════════ Persyaratan & Ketentuan ══════════ --}}
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="card-public p-4 h-100">
        <h2 class="h6 fw-bold text-dark mb-1">Persyaratan Domain .id (PANDI)</h2>
        <p class="text-muted mb-3" style="font-size:12px;max-width:46rem">
          Domain .id dikelola PANDI (Pengelola Nama Domain Internet Indonesia). Tiap ekstensi punya peruntukan dan dokumen
          persyaratan sendiri. Daftar di bawah adalah gambaran umum — dokumen final yang perlu diunggah akan ditampilkan
          setelah pesanan dibuat, dan keputusan verifikasi ada di tangan registry/PANDI.
        </p>

        <div style="overflow-x:auto">
          <table class="table align-middle mb-0 pd-req" style="font-size:13px;min-width:38rem">
            <thead>
              <tr class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em">
                <th style="width:9rem">Ekstensi</th>
                <th>Diperuntukkan bagi</th>
                <th>Dokumen umumnya</th>
              </tr>
            </thead>
            <tbody>
              <tr><td class="fw-semibold text-dark">.id</td><td>Umum — perorangan, usaha, maupun organisasi</td><td>Data identitas pendaftar yang valid; diverifikasi lewat email &amp; telepon</td></tr>
              <tr><td class="fw-semibold text-dark">.co.id</td><td>Badan hukum/usaha/organisasi bisnis yang beroperasi di Indonesia</td><td>KTP-el/paspor penanggung jawab dan NIB; surat kepemilikan merek bila domain terkait merek</td></tr>
              <tr><td class="fw-semibold text-dark">.my.id</td><td>Perorangan — blog pribadi, portofolio, personal branding</td><td>Data identitas pendaftar yang valid</td></tr>
              <tr><td class="fw-semibold text-dark">.web.id</td><td>Perorangan/usaha kecil untuk website</td><td>KTP/paspor penanggung jawab</td></tr>
              <tr><td class="fw-semibold text-dark">.biz.id</td><td>Usaha kecil yang belum berbadan hukum</td><td>KTP penanggung jawab; surat pernyataan kepemilikan usaha bila diminta</td></tr>
              <tr><td class="fw-semibold text-dark">.ac.id</td><td>Lembaga pendidikan tinggi</td><td>KTP/paspor penanggung jawab, SK/akta pendirian lembaga, surat kuasa pimpinan lembaga</td></tr>
              <tr><td class="fw-semibold text-dark">.sch.id</td><td>Sekolah (dasar hingga menengah)</td><td>KTP/paspor penanggung jawab, surat permohonan &amp; surat kuasa kepala sekolah; SK pendirian untuk pendidikan non-formal</td></tr>
              <tr><td class="fw-semibold text-dark">.or.id</td><td>Organisasi nirlaba, komunitas, yayasan</td><td>Akta/SK pendirian organisasi dan KTP penanggung jawab</td></tr>
              <tr><td class="fw-semibold text-dark">.ponpes.id</td><td>Pondok pesantren</td><td>Dokumen legalitas lembaga dan identitas penanggung jawab</td></tr>
            </tbody>
          </table>
        </div>

        <ul class="text-muted mt-3 mb-0 ps-3" style="font-size:12px">
          <li>Gunakan salinan digital (scan/foto) yang jelas, tidak terpotong, dan terbaca.</li>
          <li>Nama dan data pendaftar harus benar dan sesuai dokumen; data yang tidak valid dapat membuat pendaftaran ditolak.</li>
          <li>Proses verifikasi dokumen umumnya memakan waktu 1–3 hari kerja setelah dokumen lengkap.</li>
        </ul>
      </div>
    </div>

    <div class="col-12">
      <div class="card-public p-4 h-100">
        <h2 class="h6 fw-bold text-dark mb-3">Ketentuan Domain Premium</h2>
        <div class="row g-3" style="font-size:13px">
          <div class="col-md-6">
            <p class="fw-semibold text-dark mb-1"><i class="fa-solid fa-hashtag text-muted me-1" style="font-size:11px"></i> Premium Karakter</p>
            <ul class="text-muted mb-0 ps-3" style="font-size:12px">
              <li>Harga ditetapkan berdasarkan <strong>jumlah karakter</strong> nama (sebelum ekstensi) — makin sedikit karakternya, makin tinggi harganya.</li>
              <li>Ketersediaan dan status premium dicek ke registry saat Anda menekan tombol Cek.</li>
              <li>Nama yang lebih panjang dari batas karakter premium diperlakukan sebagai domain reguler dan dipesan lewat halaman Cek Domain biasa.</li>
            </ul>
          </div>
          <div class="col-md-6">
            <p class="fw-semibold text-dark mb-1"><i class="fa-solid fa-gem text-muted me-1" style="font-size:11px"></i> Premium Custom</p>
            <ul class="text-muted mb-0 ps-3" style="font-size:12px">
              <li>Nama domain pilihan dengan <strong>harga masing-masing</strong>; setiap nama hanya satu dan berlaku siapa cepat, dia dapat.</li>
              <li>Nama yang sudah masuk keranjang/dipesan orang lain, atau sudah terdaftar, tidak bisa dipesan lagi.</li>
            </ul>
          </div>
          <div class="col-12">
            <p class="fw-semibold text-dark mb-1"><i class="fa-solid fa-circle-info text-muted me-1" style="font-size:11px"></i> Berlaku untuk semua domain premium</p>
            <ul class="text-muted mb-0 ps-3" style="font-size:12px">
              <li>Harga yang tampil adalah harga <strong>registrasi baru 1 tahun</strong> dan bersifat tetap — tidak dapat diubah di keranjang. Biaya perpanjangan per tahun mengikuti tabel Referensi Harga dan bisa berbeda dari harga registrasi.</li>
              <li>Ketersediaan dicek ulang sebelum registrasi dikirim ke registry; bila nama ternyata sudah terdaftar pihak lain, pesanan tidak dapat diproses.</li>
              <li>Anda perlu masuk/daftar akun sebelum checkout. Dokumen persyaratan diminta setelah pesanan dibuat dan harus lengkap sebelum invoice bisa dibayar.</li>
              <li>Pembayaran bisa lewat metode yang tersedia, termasuk Transfer Manual, dan domain baru didaftarkan setelah pembayaran terkonfirmasi.</li>
              <li>Butuh bantuan memilih nama atau memahami dokumen yang diminta? Hubungi kami lewat tiket.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('premiumOrderForm')?.addEventListener('submit', function (e) {
      e.preventDefault();

      const labelInput = document.getElementById('premiumLabelInput');
      const extSelect = document.getElementById('premiumExtSelect');
      const box = document.getElementById('premiumCheckResult');
      const label = labelInput.value.trim();

      if (!label) return;

      box.style.display = 'block';
      box.innerHTML = '<span class="text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Mengecek…</span>';

      fetch('{{ route('domain-premium.check') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value,
        },
        body: JSON.stringify({ label: label, extension: extSelect.value }),
      })
        .then(res => res.json())
        .then(data => {
          if (!data.success) {
            box.innerHTML = '<span class="text-danger">' + data.message + '</span>';
            return;
          }

          if (!data.available) {
            box.innerHTML = '<span class="text-muted">' + (data.message || (data.domain + ' sudah terdaftar / tidak tersedia.')) + '</span>';
            return;
          }

          const unknownNote = data.unknown
            ? '<div class="text-muted mt-1" style="font-size:11px">Ketersediaan belum bisa dipastikan otomatis — akan dicek ulang sebelum registrasi.</div>'
            : '';
          const premiumSourceNote = !data.premium_verified
            ? '<div class="text-muted mt-1" style="font-size:11px">Status premium di atas dihitung dari jumlah karakter (belum sempat dikonfirmasi ke registry) — akan dicek ulang sebelum registrasi.</div>'
            : '';

          box.innerHTML =
            '<div class="rounded-3 px-3 py-3" style="background:#f0fdf4;border:1px solid #bbf7d0">' +
            '<strong>' + data.domain + '</strong> tersedia' + (data.is_premium ? ' sebagai domain <strong>premium</strong>' : '') + '. ' +
            'Harga registrasi 1 tahun: <strong>' + data.price_formatted + '</strong>.' +
            unknownNote + premiumSourceNote +
            '<form id="premiumAddForm" class="mt-2">' +
            '<button type="submit" class="btn btn-sm btn-theme"><i class="fa-solid fa-cart-plus" style="font-size:11px"></i> Tambah ke Keranjang</button>' +
            '</form>' +
            '<div id="premiumAddResult" class="mt-2" style="display:none"></div>' +
            '</div>';

          document.getElementById('premiumAddForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const submitBtn = ev.target.querySelector('button[type=submit]');
            submitBtn.disabled = true;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('cart.add-premium-domain') }}';
            form.innerHTML =
              '<input type="hidden" name="_token" value="' + document.querySelector('input[name=_token]').value + '">' +
              '<input type="hidden" name="tld_premium_id" value="' + data.tld_premium_id + '">' +
              '<input type="hidden" name="domain_label" value="' + data.label + '">';
            document.body.appendChild(form);
            form.submit();
          });
        })
        .catch(() => {
          box.innerHTML = '<span class="text-danger">Terjadi kesalahan, silakan coba lagi.</span>';
        });
    });
  </script>

  <script>
    (function () {
      const tabs = { karakter: document.getElementById('tabBtnKarakter'), custom: document.getElementById('tabBtnCustom') };
      const panels = { karakter: document.getElementById('panelKarakter'), custom: document.getElementById('panelCustom') };

      function showTab(name) {
        Object.keys(tabs).forEach(function (k) {
          const on = k === name;
          panels[k].hidden = !on;
          tabs[k].classList.toggle('is-active', on);
          tabs[k].setAttribute('aria-selected', on ? 'true' : 'false');
        });
      }

      Object.keys(tabs).forEach(function (k) {
        tabs[k].addEventListener('click', function () {
          showTab(k);
          history.replaceState(null, '', k === 'custom' ? '#custom-premium' : location.pathname);
        });
      });

      if (location.hash === '#custom-premium') showTab('custom');
    })();
  </script>

@endsection
