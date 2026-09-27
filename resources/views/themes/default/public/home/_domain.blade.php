  {{-- ══════════ Hero + pencarian domain ══════════ --}}
  <div style="position:relative;overflow:hidden;background:linear-gradient(160deg,#1e1b4b 0%,#312e81 40%,#4c1d95 75%,#1e1b4b 100%)">
    <div style="position:relative;max-width:44rem;margin:0 auto;padding:3.5rem 1.5rem 3rem;text-align:center">
      <span style="display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.35rem .85rem;margin-bottom:1.5rem;font-size:11px;font-weight:600;letter-spacing:.02em;background:rgba(255,255,255,.1);color:rgba(255,255,255,.85);line-height:1">
        <i class="fa-solid fa-bolt" style="font-size:10px"></i> Aktivasi otomatis, langsung online
      </span>

      <h1 style="color:#fff;font-weight:700;font-size:1.9rem;line-height:1.25;letter-spacing:-.02em;margin:0 0 1.25rem 0">
        {{ $tagline }}
      </h1>
      <p style="color:rgba(255,255,255,.65);max-width:32rem;margin:0 auto 2.5rem auto;font-size:16px;line-height:1.7">
        Cek nama domain impianmu, pilih paket hosting, bayar — layanan langsung aktif tanpa menunggu.
      </p>

      {{-- Kotak cek domain --}}
      <form method="GET" action="{{ route('domain.search') }}" id="heroSearchForm"
            style="background:#fff;border-radius:1rem;padding:.5rem;display:flex;flex-direction:column;gap:.5rem;box-shadow:0 20px 40px rgba(0,0,0,.25);max-width:34rem;margin:0 auto">
        <div style="display:flex;align-items:center;gap:.5rem;flex:1;padding:0 .75rem">
          <i class="fa-solid fa-globe" style="color:#94a3b8"></i>
          <input type="text" name="domain" value="{{ request('domain') }}"
                 placeholder="ketik nama domain impianmu…"
                 style="width:100%;padding:.6rem 0;border:0;outline:none;font-size:15px" required>
        </div>
        <button type="submit" class="btn btn-theme" style="flex-shrink:0;padding:.6rem 1.5rem">
          <i class="fa-solid fa-magnifying-glass" style="font-size:12px"></i> Cek Domain
        </button>
      </form>

      <style>
        @media (min-width: 576px) {
          #heroSearchForm { flex-direction: row !important; }
        }
      </style>

      @if ($popularTlds->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:1.5rem;margin-top:1.5rem;font-size:14px">
          @foreach ($popularTlds as $tld)
            <span style="color:rgba(255,255,255,.65)">
              <b style="color:#fff">{{ $tld->extension }}</b>
              Rp {{ number_format($tld->register_price, 0, ',', '.') }}
            </span>
          @endforeach
        </div>
      @endif
    </div>
  </div>
