{{-- ══════════ Pengumuman (tema Modern) ══════════ --}}
<section class="mx-section mx-section-alt">
  <div class="mx-container">
    <div class="mx-head">
      <div><span class="mx-eyebrow">Kabar</span><h2>Kabar terbaru</h2></div>
      <a href="{{ route('announcements.index') }}" class="mx-link">Lihat semua</a>
    </div>
    <div class="mx-grid">
      @foreach ($announcements as $item)
        <a href="{{ route('announcements.show', $item->slug) }}" class="mx-tile" style="min-height:0">
          <span class="idx text-uppercase">{{ $item->category }}</span>
          <h3 style="font-size:1.05rem;line-height:1.45">{{ $item->title }}</h3>
          <span class="go" style="font-weight:500;color:#71717a">{{ $item->published_at?->format('d M Y') }} <i class="fa-solid fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
