@extends('public.layout')

@php
  $seoTitle = 'Domain Premium';
  $seoDescription = 'Cek & pesan domain premium .id — harga tetap berdasarkan jumlah karakter, bisa langsung dibayar termasuk lewat Transfer Manual.';
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

@endsection
