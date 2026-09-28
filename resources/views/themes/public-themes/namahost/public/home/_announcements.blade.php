{{-- ══════════ Pengumuman (tema NamaHost) ══════════ --}}
<section class="py-5 bg-body-tertiary">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
      <div>
        <span class="badge text-bg-warning mb-2">Kabar</span>
        <h2 class="mb-0">Kabar terbaru</h2>
      </div>
      <a href="{{ route('announcements.index') }}" class="fw-semibold text-decoration-none">Lihat semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
      @foreach ($announcements as $item)
        <div class="col-md-6 col-lg-4">
          <a href="{{ route('announcements.show', $item->slug) }}" class="card-public d-block p-4 h-100 text-decoration-none">
            <span class="badge badge-soft-secondary text-uppercase mb-2">{{ $item->category }}</span>
            <h3 class="h6 mb-2 text-body">{{ $item->title }}</h3>
            <small class="text-body-secondary">{{ $item->published_at?->format('d M Y') }}</small>
          </a>
        </div>
      @endforeach
    </div>
  </div>
</section>
