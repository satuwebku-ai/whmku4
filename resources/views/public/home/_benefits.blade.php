{{-- ══════════ Keunggulan ══════════ --}}
      <div style="position:relative;max-width:72rem;margin:2.5rem auto 0 auto;padding:0 1.5rem;z-index:10">
      <div style="display:flex;flex-wrap:wrap;gap:1.5rem">
        @php
          $benefits = [
            ['icon' => 'fa-bolt',          'title' => 'Aktif Otomatis',    'desc' => 'Akun hosting dibuat otomatis begitu pembayaran masuk.'],
            ['icon' => 'fa-shield-halved', 'title' => 'Aman & Terjaga',    'desc' => 'SSL gratis, backup rutin, dan proteksi berlapis.'],
            ['icon' => 'fa-headset',       'title' => 'Dukungan Responsif','desc' => 'Tim support siap membantu lewat tiket dan chat.'],
            ['icon' => 'fa-wallet',        'title' => 'Bayar Mudah',       'desc' => 'Transfer bank, e-wallet, kartu kredit, dan QRIS.'],
          ];
        @endphp

        @foreach ($benefits as $item)
          <div style="flex:1 1 240px;min-width:240px">
            <div class="card-public" style="padding:1.5rem;height:100%">
              <span style="display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:1rem;margin-bottom:1rem;background:rgba(79,70,229,.1);color:#4f46e5;font-size:16px">
                <i class="fa-solid {{ $item['icon'] }}"></i>
              </span>
              <h3 style="font-weight:600;color:#1e293b;font-size:15px;margin:0 0 .5rem 0">{{ $item['title'] }}</h3>
              <p style="color:#64748b;font-size:13px;line-height:1.7;margin:0">{{ $item['desc'] }}</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
