  {{-- ══════════ Ajakan ══════════ --}}
  @php $cta = \App\Support\HomeContent::cta(\App\Support\HomeContent::DEFAULT_CTA); @endphp
  <div style="max-width:72rem;margin:0 auto;padding:5rem 1.5rem">
    <div style="border-radius:1rem;padding:4rem 2rem;text-align:center;position:relative;overflow:hidden;background:linear-gradient(135deg,#4f46e5 0%,#6366F1 50%,#7c3aed 100%)">
      <h2 style="font-weight:700;color:#fff;font-size:1.75rem;letter-spacing:-.02em;margin:0 0 .75rem 0">{{ $cta['title'] }}</h2>
      <p style="color:rgba(255,255,255,.75);font-size:15px;line-height:1.7;max-width:30rem;margin:0 auto 2rem auto">
        {{ $cta['text'] }}
      </p>
      <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.75rem">
        <a href="{{ route('catalog.index') }}" class="btn" style="background:#fff;color:#4f46e5;padding:.6rem 1.5rem">
          <i class="fa-solid fa-server" style="font-size:12px"></i> {{ $cta['primary'] }}
        </a>
        <a href="{{ route('client.register') }}" class="btn" style="background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.3);padding:.6rem 1.5rem">
          {{ $cta['secondary'] }}
        </a>
      </div>
    </div>
  </div>
