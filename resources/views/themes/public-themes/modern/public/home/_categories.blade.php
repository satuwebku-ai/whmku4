{{-- ══════════ Kategori ══════════ --}}
  <div style="background:#fff;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;padding:5rem 0">
      <div style="max-width:72rem;margin:0 auto;padding:0 1.5rem">
        <div style="text-align:center;margin-bottom:3rem">
          <h2 style="font-weight:700;color:#1e293b;font-size:1.75rem;letter-spacing:-.02em;margin:0 0 .5rem 0">Layanan Kami</h2>
          <p style="color:#64748b;font-size:15px;margin:0">Pilih kategori yang sesuai kebutuhanmu.</p>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:1.5rem">
          @foreach ($categories as $category)
            <div style="flex:1 1 280px;min-width:260px;max-width:360px">
              <a href="{{ $category->publicUrl() }}" class="card-public" style="display:block;padding:1.5rem;text-decoration:none;height:100%">
                <span style="display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:1rem;margin-bottom:1rem;background:rgba(79,70,229,.1);color:#4f46e5;font-size:18px">
                  <i class="fa-solid fa-server"></i>
                </span>
                <h3 style="font-weight:600;color:#1e293b;font-size:16px;margin:0 0 .5rem 0">{{ $category->name }}</h3>
                @if ($category->description)
                  <p style="color:#64748b;font-size:13px;line-height:1.7;margin:0 0 .75rem 0">{{ Str::limit($category->description, 90) }}</p>
                @endif
                <span style="display:inline-flex;align-items:center;gap:.25rem;color:var(--lumora-theme);font-weight:500;font-size:13px">
                  {{ $category->products_count }} paket <i class="fa-solid fa-arrow-right" style="font-size:10px"></i>
                </span>
              </a>
            </div>
          @endforeach
        </div>
      </div>
    </div>
