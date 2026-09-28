{{-- ══════════ Paket unggulan ══════════ --}}
    <div style="max-width:72rem;margin:0 auto;padding:5rem 1.5rem">
    <div style="text-align:center;margin-bottom:3rem">
      <h2 style="font-weight:700;color:#1e293b;font-size:1.75rem;letter-spacing:-.02em;margin:0 0 .5rem 0">Paket Hosting Pilihan</h2>
      <p style="color:#64748b;font-size:15px;margin:0">Mulai kecil, naik kelas kapan saja tanpa pindah server.</p>
    </div>

    @if ($featured->isEmpty())
      <div class="card-public" style="padding:3rem;text-align:center">
        <p style="color:#64748b;font-size:14px;margin:0 0 .25rem 0">Katalog sedang disiapkan.</p>
        <p style="color:#94a3b8;font-size:12px;margin:0">
          Belum ada produk yang bisa ditampilkan — tambahkan lewat menu Produk di admin panel.
        </p>
      </div>
    @else
      <div style="display:flex;flex-wrap:wrap;gap:1.5rem">
        @foreach ($featured as $product)
          <div style="flex:1 1 300px;min-width:280px;max-width:400px">
            @include('public.catalog._product-card', ['product' => $product])
          </div>
        @endforeach
      </div>

      <div style="text-align:center;margin-top:3rem">
        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary" style="padding:.6rem 1.5rem">
          Lihat Semua Paket <i class="fa-solid fa-arrow-right" style="font-size:12px"></i>
        </a>
      </div>
    @endif
  </div>
