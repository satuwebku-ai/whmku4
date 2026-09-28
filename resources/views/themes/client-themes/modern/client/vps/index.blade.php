@extends('client.layout')
@section('title', 'VPS Saya')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">VPS Saya</h1>
      <p class="text-muted mb-0">Kelola mesin virtual Anda — nyalakan, matikan, dan pantau pemakaian.</p>
    </div>
    <a href="{{ route('client.balance') }}" class="card-public px-3 py-2 text-decoration-none">
      <span class="d-block text-muted" style="font-size:11px">Saldo Anda</span>
      <span class="fw-bold {{ (float) $client->balance <= 0 ? 'text-danger' : 'text-dark' }}">
        Rp {{ number_format((float) $client->balance, 0, ',', '.') }}
      </span>
    </a>
  </div>

  <div class="d-flex flex-column gap-3">
    @forelse ($accounts as $vps)
      @php
        $rate = $rates[$vps->id] ?? null;
        $hoursLeft = ($rate && $rate > 0) ? floor((float) $client->balance / $rate) : null;
        $spec = $vps->hasVmSpec() ? $vps->vmSpec() : null;
      @endphp
      <a href="{{ route('client.vps.show', $vps) }}" class="dash-card dash-card-hover p-4 text-decoration-none d-block">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
          <div class="d-flex align-items-start gap-3 min-w-0">
            <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 position-relative" style="width:40px;height:40px;background:{{ $vps->status === 'active' ? 'rgba(21,128,61,.1)' : 'rgba(100,116,139,.1)' }};color:{{ $vps->status === 'active' ? '#15803d' : '#64748b' }}">
              <i class="fa-solid fa-server" style="font-size:15px"></i>
              @if ($vps->status === 'active')
                <span class="position-absolute rounded-circle" style="top:-2px;right:-2px;width:9px;height:9px;background:#22c55e;border:2px solid #fff"></span>
              @endif
            </span>
            <div class="min-w-0">
              <p class="fw-semibold text-dark mb-0">{{ $vps->domain }}</p>
              @if ($spec)
                <p class="text-muted mb-0" style="font-size:12px">
                  {{ $spec['vcpu'] }} vCPU · {{ $spec['ram'] }} MB RAM · {{ $spec['disk'] }} GB Disk
                </p>
              @endif
              @if ($vps->serverModel)
                <p class="text-muted mb-0" style="font-size:11px">{{ $vps->serverModel->name }}</p>
              @endif
            </div>
          </div>

          <div class="text-end flex-shrink-0">
            <span class="badge {{ ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'suspended' => 'badge-soft-danger'][$vps->status] ?? 'badge-soft-secondary' }}">
              {{ ['active' => 'Menyala', 'pending' => 'Menunggu', 'suspended' => 'Mati'][$vps->status] ?? ucfirst($vps->status) }}
            </span>

            @if ($vps->billing_mode === 'deposit' && $rate)
              <p class="fw-semibold text-dark mt-2 mb-0" style="font-size:13px">
                Rp {{ number_format($rate, 2, ',', '.') }} / jam
              </p>
              @if ($hoursLeft !== null)
                <p class="mb-0" style="font-size:11px;{{ $hoursLeft < 24 ? 'color:#b91c1c;font-weight:600' : 'color:#94a3b8' }}">
                  @if ($hoursLeft < 24)
                    <i class="fa-solid fa-triangle-exclamation"></i> Sisa ± {{ $hoursLeft }} jam
                  @else
                    ± {{ intdiv($hoursLeft, 24) }} hari lagi
                  @endif
                </p>
              @endif
            @elseif ($vps->billing_mode === 'invoice')
              <p class="text-muted mt-2 mb-0" style="font-size:11px">
                Rp {{ number_format((float) $vps->price, 0, ',', '.') }} / {{ str_replace('_', ' ', $vps->billing_cycle) }}
              </p>
            @endif
          </div>
        </div>
      </a>
    @empty
      <div class="dash-card p-5 text-center">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:44px;height:44px;background:#f1f5f9;color:#94a3b8">
          <i class="fa-solid fa-server"></i>
        </span>
        <p class="fw-medium text-dark mb-1">Belum punya VPS</p>
        <p class="text-muted mb-3" style="font-size:14px">Pilih paket VPS dan mulai dalam hitungan menit.</p>
        <a href="{{ route('catalog.index') }}" class="btn btn-theme mx-auto" style="width:fit-content">Lihat Paket VPS</a>
      </div>
    @endforelse
  </div>

  @if ($accounts->isNotEmpty())
    <p class="text-muted mt-3 mb-0" style="font-size:11px">
      <i class="fa-solid fa-circle-info"></i>
      VPS bersistem saldo dipotong otomatis tiap jam selama menyala. Matikan VPS yang tidak dipakai untuk menghemat saldo.
    </p>
  @endif
@endsection
