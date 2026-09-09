{{-- ══════════ Pengumuman ══════════ --}}
      <div style="max-width:72rem;margin:0 auto;padding:5rem 1.5rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:3rem">
        <h2 style="font-weight:700;color:#1e293b;font-size:1.4rem;letter-spacing:-.02em;margin:0">Kabar Terbaru</h2>
        <a href="{{ route('announcements.index') }}" style="text-decoration:none;color:var(--lumora-theme);font-weight:500;font-size:14px">Lihat semua</a>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:1.5rem">
        @foreach ($announcements as $item)
          <div style="flex:1 1 280px;min-width:260px">
            <a href="{{ route('announcements.show', $item->slug) }}" class="card-public" style="display:block;padding:1.5rem;text-decoration:none;height:100%">
              <span class="badge-public-inactive" style="text-transform:capitalize;margin-bottom:.75rem;display:inline-block">{{ $item->category }}</span>
              <h3 style="font-weight:600;color:#1e293b;font-size:15px;line-height:1.5;margin:0 0 .5rem 0">{{ $item->title }}</h3>
              <p style="color:#94a3b8;font-size:12px;margin:0">{{ $item->published_at?->format('d M Y') }}</p>
            </a>
          </div>
        @endforeach
      </div>
    </div>
