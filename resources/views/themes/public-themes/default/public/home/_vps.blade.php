{{-- ══════════ Paket VPS ══════════ --}}
{{-- Section ini TIDAK dirender sama sekali kalau belum ada paket VPS
     (lihat $sectionData di CatalogController::homeData), jadi tidak
     perlu penanganan "kosong" seperti di section hosting. --}}
<div style="background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;padding:5rem 0">
  <div style="max-width:72rem;margin:0 auto;padding:0 1.5rem">
    <div style="text-align:center;margin-bottom:3rem">
      <h2 style="font-weight:700;color:#1e293b;font-size:1.75rem;letter-spacing:-.02em;margin:0 0 .5rem 0">VPS &amp; Cloud Server</h2>
      <p style="color:#64748b;font-size:15px;margin:0">Kontrol penuh dengan akses root, aktif otomatis dalam hitungan menit.</p>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:1.5rem">
      @foreach ($vpsProducts as $product)
        <div style="flex:1 1 300px;min-width:280px;max-width:400px">
          @include('public.catalog._product-card', ['product' => $product])
        </div>
      @endforeach
    </div>

    <div style="text-align:center;margin-top:2.5rem">
      <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary">
        Lihat Semua Paket <i class="fa-solid fa-arrow-right" style="font-size:11px"></i>
      </a>
    </div>
  </div>
</div>
