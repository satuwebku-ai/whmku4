{{-- ══════════ Ajakan (tema Modern) ══════════ --}}
@php $cta = \App\Support\HomeContent::cta(\App\Support\HomeContent::DEFAULT_CTA); @endphp
<section class="mx-section">
  <div class="mx-container">
    <div class="mx-cta">
      <div>
        <h2>{{ $cta['title'] }}</h2>
        <p>{{ $cta['text'] }}</p>
      </div>
      <div class="btns">
        <a href="{{ route('catalog.index') }}" class="btn" style="background:#fff;color:#18181b;padding:.7rem 1.6rem">{{ $cta['primary'] }}</a>
        <a href="{{ route('client.register') }}" class="btn btn-theme" style="padding:.7rem 1.6rem">{{ $cta['secondary'] }}</a>
      </div>
    </div>
  </div>
</section>
