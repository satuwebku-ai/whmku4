<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pratinjau — {{ $meta['label'] }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/vendor/bootstrap-5.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/lumora-public.css') }}?v={{ @filemtime(public_path('assets/css/lumora-public.css')) ?: time() }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>body{background:#f1f5f9;min-height:100vh;padding:2.5rem 1rem}</style>
</head>
<body>

  <div class="mx-auto mb-4 text-center" style="max-width:32rem">
    <p class="text-muted mb-0" style="font-size:12px">
      <i class="fa-solid fa-circle-info"></i>
      Pratinjau dengan data contoh — bukan tampilan persis di tiap aplikasi email/WhatsApp,
      tapi cukup mewakili susunan &amp; isinya.
    </p>
  </div>

  {{-- Kartu Email --}}
  <div class="mx-auto bg-white rounded-4 shadow-sm overflow-hidden mb-4" style="max-width:32rem">
    <div style="height:5px;background:#4f46e5"></div>
    <div class="px-4 py-3 text-center" style="background:#1e293b">
      @if ($siteLogo)
        <img src="{{ route('branding.file', $siteLogo) }}" alt="{{ $siteName }}" style="height:32px">
      @else
        <p class="text-white fw-bold mb-0">{{ $siteName }}</p>
      @endif
    </div>
    <div class="p-4">
      <p class="text-muted mb-1" style="font-size:11px">Subjek</p>
      <p class="fw-semibold text-dark mb-4">{{ $subject ?: '(kosong)' }}</p>

      <div class="text-muted d-flex flex-column gap-3" style="font-size:14px;line-height:1.7">
        @forelse ($lines as $line)
          <p class="mb-0">{!! preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', e($line)) !!}</p>
        @empty
          <p class="fst-italic mb-0" style="color:#cbd5e1">(isi email kosong)</p>
        @endforelse
      </div>

      @if ($action)
        <div class="mt-4">
          <span class="d-inline-block text-white fw-semibold rounded-3" style="background:#4f46e5;font-size:14px;padding:.65rem 1.4rem;box-shadow:0 6px 16px -6px rgba(79,70,229,.55)">
            {{ $action['label'] }}
          </span>
          <p class="text-muted mt-1 mb-0" style="font-size:11px">&#8629; {{ $action['url'] }}</p>
        </div>
      @endif

      @if ($promoBanner)
        <div class="mt-4 rounded-3 overflow-hidden border">
          <img src="{{ route('banner.file', $promoBanner->image) }}" alt="{{ $promoBanner->title }}" class="w-100 d-block">
        </div>
        <p class="text-muted mt-2 mb-0" style="font-size:11px">
          <i class="fa-solid fa-circle-info"></i> Banner ini ikut tampil karena ada Banner Promo aktif untuk halaman "Email Transaksional".
        </p>
      @endif
    </div>
  </div>

  {{-- Gelembung WhatsApp --}}
  @if (trim((string) $bodyWhatsapp) !== '')
    <div class="mx-auto" style="max-width:32rem">
      <p class="text-muted mb-2 text-center" style="font-size:12px"><i class="fa-brands fa-whatsapp"></i> Pratinjau WhatsApp</p>
      <div class="rounded-4 p-3 mx-auto shadow-sm" style="background:#dcf8c6;color:#1e293b;font-size:14px;white-space:pre-line;max-width:28rem;border-top-left-radius:0!important;font-family:-apple-system,sans-serif">{!! preg_replace('/\*(.+?)\*/', '<b>$1</b>', e($bodyWhatsapp)) !!}</div>
    </div>
  @endif

  {{-- Gelembung SMS --}}
  @if (trim((string) $bodySms) !== '')
    @php
      $smsLen = mb_strlen($bodySms);
      $smsSegments = (int) ceil(max($smsLen, 1) / 160);
    @endphp
    <div class="mx-auto mt-4" style="max-width:32rem">
      <p class="text-muted mb-2 text-center" style="font-size:12px"><i class="fa-solid fa-comment-sms"></i> Pratinjau SMS</p>
      <div class="rounded-4 p-3 mx-auto shadow-sm" style="background:#e2e8f0;color:#1e293b;font-size:14px;white-space:pre-line;max-width:28rem;font-family:-apple-system,sans-serif">{{ $bodySms }}</div>
      <p class="text-muted mt-2 mb-0 text-center" style="font-size:11px">
        {{ $smsLen }} karakter — {{ $smsSegments }} segmen SMS
        @if ($smsSegments > 1)
          <span class="text-warning">(lebih dari 1 segmen = biaya kirim lebih dari 1x)</span>
        @endif
      </p>
    </div>
  @endif

  <div class="mx-auto text-center mt-4" style="max-width:32rem">
    <button onclick="window.close()" class="btn btn-link text-muted p-0" style="font-size:12px;text-decoration:none">Tutup tab ini</button>
  </div>

</body>
</html>
