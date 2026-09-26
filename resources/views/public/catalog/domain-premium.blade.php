@extends('public.layout')

@php
  $seoTitle = 'Domain Premium';
  $seoDescription = 'Daftar harga domain premium .id dan cek domain premium generik (.com, .net, dan lainnya).';
@endphp

@section('content')

  <div class="mb-4">
    @include('public._promo-banner-carousel')
  </div>

  <div class="mb-4">
    <p class="text-muted mb-2" style="font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase">Domain Premium</p>
    <h1 class="fw-bold text-dark mb-2" style="font-size:1.6rem">Domain Premium</h1>
    <p class="text-muted mb-0" style="max-width:44rem">
      Domain premium adalah nama domain bernilai tinggi yang dipatok registry dengan harga khusus,
      berbeda dari harga domain reguler. Untuk keluarga <strong>.id</strong>, harganya tetap berdasarkan
      jumlah karakter dan bisa dilihat langsung di bawah. Untuk ekstensi lain (.com, .net, dan sejenisnya),
      harga premium ditentukan per nama domain — cek dulu, lalu pesan lewat tiket.
    </p>
  </div>

  {{-- ══════════ Harga tetap keluarga .id ══════════ --}}
  <div class="card-public p-4 mb-4">
    <h2 class="h6 fw-bold text-dark mb-3">Harga Domain Premium .id</h2>

    @if ($idFamily['error'])
      <div class="rounded-3 px-3 py-2 mb-3" style="background:#fef2f2;border:1px solid #fecaca;font-size:13px;color:#b91c1c">
        Daftar harga sedang tidak bisa dimuat ({{ $idFamily['error'] }}). Silakan coba lagi beberapa saat lagi.
      </div>
    @elseif (empty($idFamily['rows']))
      <p class="text-muted mb-0" style="font-size:13px">Daftar harga belum tersedia saat ini.</p>
    @else
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

      <p class="text-muted mt-3 mb-0" style="font-size:12px">
        Harga di atas berlaku untuk registrasi baru 1 tahun. Untuk memesan domain premium .id,
        hubungi kami lewat tiket — dokumen persyaratan sama seperti pendaftaran domain .id biasa.
      </p>
    @endif
  </div>

  {{-- ══════════ Cek domain premium generik ══════════ --}}
  <div class="card-public p-4 mb-4">
    <h2 class="h6 fw-bold text-dark mb-1">Cek Domain Premium Lainnya</h2>
    <p class="text-muted mb-3" style="font-size:13px">
      Berlaku untuk ekstensi:
      @foreach ($genericExtensions as $i => $ext)
        <span class="fw-semibold text-dark">{{ $ext }}</span>{{ $i < count($genericExtensions) - 1 ? ',' : '.' }}
      @endforeach
      Harga domain premium generik berbeda-beda per nama dan ditentukan langsung oleh registry, jadi
      tidak bisa ditampilkan sebagai daftar harga tetap.
    </p>

    <form id="premiumCheckForm" class="d-flex flex-column flex-sm-row gap-2" style="max-width:34rem">
      @csrf
      <input type="text" id="premiumCheckInput" placeholder="contoh: toko.com"
             class="form-control" style="font-size:14px" required autocomplete="off">
      <button type="submit" class="btn btn-theme flex-shrink-0">
        <i class="fa-solid fa-magnifying-glass" style="font-size:12px"></i> Cek Domain
      </button>
    </form>

    <div id="premiumCheckResult" class="mt-3" style="display:none;font-size:13px"></div>
  </div>

  <script>
    document.getElementById('premiumCheckForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const input = document.getElementById('premiumCheckInput');
      const box = document.getElementById('premiumCheckResult');
      const domain = input.value.trim();

      if (!domain) return;

      box.style.display = 'block';
      box.innerHTML = '<span class="text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Mengecek…</span>';

      fetch('{{ route('domain-premium.check') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value,
        },
        body: JSON.stringify({ domain_name: domain }),
      })
        .then(res => res.json())
        .then(data => {
          if (!data.success) {
            box.innerHTML = '<span class="text-danger">' + data.message + '</span>';
            return;
          }

          if (!data.available) {
            box.innerHTML = '<span class="text-muted">Domain <strong>' + data.domain + '</strong> sudah terdaftar / tidak tersedia.</span>';
            return;
          }

          if (data.is_premium) {
            box.innerHTML =
              '<div class="rounded-3 px-3 py-2" style="background:#fef3c7;border:1px solid #fde68a">' +
              '<strong>' + data.domain + '</strong> tersedia sebagai domain <strong>premium</strong>. ' +
              'Harganya khusus dan diproses manual oleh tim kami.<br>' +
              '<a href="{{ route('client.tickets.create') }}?department=sales&subject=' + encodeURIComponent('Pesan Domain Premium: ' + data.domain) +
              '&message=' + encodeURIComponent('Saya ingin memesan domain premium: ' + data.domain) +
              '" class="btn btn-sm btn-theme mt-2"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Pesan lewat Tiket</a>' +
              '</div>';
            return;
          }

          box.innerHTML =
            '<span class="text-success">' + data.domain + ' tersedia dan bukan domain premium — daftarkan lewat halaman <a href="{{ route('domain.search') }}">Cek Domain</a> biasa.</span>';
        })
        .catch(() => {
          box.innerHTML = '<span class="text-danger">Terjadi kesalahan, silakan coba lagi.</span>';
        });
    });
  </script>

@endsection
