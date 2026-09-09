@extends('layouts.admin')

@section('title', 'Layanan VPS')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Layanan VPS</h1>
      <p class="small text-muted mb-0">Semua layanan yang berjalan di provider cloud (VM/VPS), beserta status tagihan per jamnya.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('admin.servers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-server" style="font-size:11px"></i> Kelola Server Cloud
      </a>
      <a href="{{ route('admin.vps.create') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah VPS
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    @php
      $cards = [
        ['label' => 'Total Layanan VPS', 'value' => $stats['total'], 'icon' => 'fa-desktop', 'fg' => '#4f46e5'],
        ['label' => 'Aktif Berjalan', 'value' => $stats['active'], 'icon' => 'fa-circle-play', 'fg' => '#047857'],
        ['label' => 'Mode Deposit', 'value' => $stats['deposit'], 'icon' => 'fa-wallet', 'fg' => '#b45309'],
        ['label' => 'Estimasi Pendapatan / Jam', 'value' => 'Rp ' . number_format($stats['hourly_revenue'], 2, ',', '.'), 'icon' => 'fa-coins', 'fg' => '#0891b2'],
      ];
    @endphp
    @foreach ($cards as $card)
      <div class="col-6 col-lg-3">
        <div class="card border rounded-4 p-3 h-100">
          <i class="fa-solid {{ $card['icon'] }} mb-2" style="font-size:14px;color:{{ $card['fg'] }}"></i>
          <p class="fw-bold text-dark mb-0" style="font-size:1.25rem">{{ $card['value'] }}</p>
          <p class="text-muted mb-0" style="font-size:11px">{{ $card['label'] }}</p>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:13px">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Layanan</th>
            <th class="py-3">Klien</th>
            <th class="py-3">Server</th>
            <th class="py-3">Spesifikasi</th>
            <th class="py-3">Mode Tagihan</th>
            <th class="text-end py-3">Tarif / Jam</th>
            <th class="py-3">Saldo Klien</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($accounts as $account)
            @php
              $spec = $account->hasVmSpec() ? $account->vmSpec() : null;
              $rate = $rates[$account->id] ?? null;
              $balance = (float) ($account->client->balance ?? 0);
              $hoursLeft = ($rate && $rate > 0) ? floor($balance / $rate) : null;
            @endphp
            <tr>
              <td class="px-4 py-3">
                <span class="fw-medium text-dark">{{ $account->domain }}</span>
                <p class="text-muted mb-0" style="font-size:10px">{{ $account->serverModel->name ?? '' }}</p>
                @if ($account->provision_status === 'failed' && $account->provision_message)
                  <p class="mb-0 mt-1" style="font-size:10px;color:#b91c1c">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ Str::limit($account->provision_message, 90) }}
                  </p>
                @endif
              </td>
              <td class="py-3 text-muted">{{ $account->client->name ?? '—' }}</td>
              <td class="py-3 text-muted" style="font-size:12px">{{ $account->serverModel->name ?? '—' }}</td>
              <td class="py-3 text-muted" style="font-size:11px">
                @if ($spec)
                  {{ $spec['vcpu'] }} vCPU · {{ $spec['ram'] }} MB · {{ $spec['disk'] }} GB
                  <span class="d-block">{{ $spec['os_name'] }}@if ($spec['backup_enabled']) · backup @endif</span>
                @else
                  <span style="color:#b45309">Spek JSON belum diisi</span>
                @endif
              </td>
              <td class="py-3">
                <span class="badge {{ $account->billing_mode === 'deposit' ? 'badge-soft-warning' : 'badge-soft-secondary' }}">
                  {{ $account->billing_mode === 'deposit' ? 'Deposit / jam' : 'Invoice' }}
                </span>
              </td>
              <td class="text-end py-3 fw-semibold text-dark">
                {{ $rate !== null ? 'Rp ' . number_format($rate, 2, ',', '.') : '—' }}
              </td>
              <td class="py-3">
                <span class="{{ $balance <= 0 ? 'text-danger fw-semibold' : 'text-dark' }}">
                  Rp {{ number_format($balance, 0, ',', '.') }}
                </span>
                @if ($hoursLeft !== null && $account->billing_mode === 'deposit')
                  <span class="d-block text-muted" style="font-size:10px">± {{ $hoursLeft }} jam lagi</span>
                @endif
              </td>
              <td class="py-3">
                <span class="badge {{ ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'suspended' => 'badge-soft-danger'][$account->status] ?? 'badge-soft-secondary' }}">
                  {{ ucfirst($account->status) }}
                </span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  @if ($account->provision_status === 'failed')
                    <button type="button" class="btn btn-primary btn-sm"
                            onclick="document.getElementById('retry-{{ $account->id }}').classList.toggle('d-none')">
                      <i class="fa-solid fa-rotate" style="font-size:11px"></i> Coba Lagi
                    </button>
                  @elseif ($account->provision_status === 'provisioned' && $account->username)
                    <form method="POST" action="{{ route('admin.vps.power', $account) }}">
                      @csrf
                      <input type="hidden" name="action" value="{{ $account->status === 'active' ? 'stop' : 'start' }}">
                      <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center"
                              style="width:32px;height:32px;padding:0"
                              title="{{ $account->status === 'active' ? 'Matikan VM' : 'Nyalakan VM' }}">
                        <i class="fa-solid {{ $account->status === 'active' ? 'fa-power-off' : 'fa-play' }}" style="font-size:11px"></i>
                      </button>
                    </form>
                  @endif

                  @if ($account->provision_status === 'provisioned' && $account->username)
                    <form method="POST" action="{{ route('admin.vps.attach-ip', $account) }}"
                          data-confirm="Alokasikan & pasang IP publik untuk {{ $account->domain }}? IP publik menambah biaya di provider."
                          data-confirm-title="Pasang IP Publik" data-confirm-style="warn" data-confirm-label="Ya, Pasang">
                      @csrf
                      <button type="submit" class="btn btn-outline-warning btn-sm d-inline-flex align-items-center justify-content-center"
                              style="width:32px;height:32px;padding:0" title="Pasang IP publik">
                        <i class="fa-solid fa-globe" style="font-size:11px"></i>
                      </button>
                    </form>
                  @endif

                  @if ($account->serverModel)
                    <a href="{{ route('admin.servers.diagnostics', $account->serverModel) }}"
                       class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center"
                       style="width:32px;height:32px;padding:0" title="Diagnosa server & daftar VM">
                      <i class="fa-solid fa-stethoscope" style="font-size:11px"></i>
                    </a>
                  @endif

                  <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center"
                          style="width:32px;height:32px;padding:0" title="Hapus"
                          onclick="document.getElementById('del-{{ $account->id }}').classList.toggle('d-none')">
                    <i class="fa-regular fa-trash-can" style="font-size:11px"></i>
                  </button>
                </div>
              </td>
            </tr>

            {{-- Baris hapus: sengaja memisahkan "hapus VM" dari "hapus
                 catatan", karena keduanya berakibat sangat berbeda. --}}
            <tr id="del-{{ $account->id }}" class="d-none">
              <td colspan="9" class="px-4 py-3" style="background:#fef2f2">
                <p class="fw-semibold mb-2" style="font-size:13px;color:#b91c1c">
                  Hapus "{{ $account->domain }}" — pilih tindakan:
                </p>
                <div class="d-flex gap-2 flex-wrap">
                  @if ($account->provision_status === 'provisioned')
                    <form method="POST" action="{{ route('admin.vps.destroy', $account) }}"
                          data-confirm="HAPUS PERMANEN VM {{ $account->domain }} beserta seluruh datanya di provider? Tidak bisa dikembalikan."
                          data-confirm-title="Hapus VM Permanen" data-confirm-style="danger" data-confirm-label="Ya, Hapus VM">
                      @csrf @method('DELETE')
                      <input type="hidden" name="hapus_vm" value="1">
                      <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:11px"></i> Hapus VM + Catatan
                      </button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('admin.vps.destroy', $account) }}"
                        data-confirm="Hapus catatan saja? VM di provider TIDAK akan disentuh dan tetap menagih biaya ke akunmu."
                        data-confirm-title="Hapus Catatan Saja" data-confirm-style="warn" data-confirm-label="Ya, Hapus Catatan">
                    @csrf @method('DELETE')
                    <input type="hidden" name="hapus_vm" value="0">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus Catatan Saja</button>
                  </form>
                  <button type="button" class="btn btn-outline-secondary btn-sm"
                          onclick="document.getElementById('del-{{ $account->id }}').classList.add('d-none')">Batal</button>
                </div>
                <p class="text-muted mt-2 mb-0" style="font-size:11px">
                  "Hapus Catatan Saja" dipakai kalau VM sudah dihapus manual di provider — jangan dipakai untuk VM yang masih berjalan.
                </p>
              </td>
            </tr>

            @if ($account->provision_status === 'failed')
              <tr id="retry-{{ $account->id }}" class="d-none">
                <td colspan="9" class="px-4 py-3" style="background:#fffbeb">
                  <form method="POST" action="{{ route('admin.vps.retry', $account) }}" class="d-flex align-items-end gap-2 flex-wrap">
                    @csrf
                    @php $rs = $account->hasVmSpec() ? $account->vmSpec() : ['vcpu' => 2, 'ram' => 1024, 'disk' => 20]; @endphp
                    <div>
                      <label class="form-label small fw-medium text-dark mb-1">vCPU</label>
                      <input type="number" name="vcpu" value="{{ max(2, $rs['vcpu']) }}" min="1" class="form-control form-control-sm" style="width:5rem">
                    </div>
                    <div>
                      <label class="form-label small fw-medium text-dark mb-1">RAM (MB)</label>
                      <input type="number" name="ram" value="{{ $rs['ram'] }}" step="512" class="form-control form-control-sm" style="width:7rem">
                    </div>
                    <div>
                      <label class="form-label small fw-medium text-dark mb-1">Disk (GB)</label>
                      <input type="number" name="disk" value="{{ $rs['disk'] }}" class="form-control form-control-sm" style="width:6rem">
                    </div>
                    <div>
                      <label class="form-label small fw-medium text-dark mb-1">Username VM</label>
                      <input type="text" name="username" value="ubuntu" class="form-control form-control-sm" style="width:9rem" required>
                    </div>
                    <div>
                      <label class="form-label small fw-medium text-dark mb-1">Password VM</label>
                      <input type="text" name="password" id="rp-{{ $account->id }}" class="form-control form-control-sm" style="width:13rem" required minlength="8">
                    </div>
                    <button type="button" onclick="genPass('rp-{{ $account->id }}')" class="btn btn-outline-secondary btn-sm">
                      <i class="fa-solid fa-dice" style="font-size:11px"></i>
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">Buat VM Sekarang</button>
                  </form>
                  <p class="text-muted mt-2 mb-0" style="font-size:11px">
                    <i class="fa-solid fa-shield-halved text-success"></i>
                    <b>Aman diklik</b> — kalau VM bernama "{{ $account->domain }}" sudah ada di provider,
                    sistem memakai VM itu (tidak membuat baru), dan spek yang tersimpan otomatis disesuaikan
                    dengan mesin aslinya. Spek di atas hanya dipakai kalau VM benar-benar belum ada.
                  </p>
                </td>
              </tr>
            @endif
          @empty
            <tr>
              <td colspan="9" class="text-center py-5">
                <p class="text-muted mb-1" style="font-size:14px">Belum ada layanan VPS.</p>
                <p class="text-muted mb-0" style="font-size:11px">
                  Layanan akan muncul di sini setelah ada hosting account yang menunjuk ke server bertipe cloud (mis. IDCloudHost).
                </p>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($accounts->hasPages())
      <div class="px-4 py-3 border-top">{{ $accounts->links('pagination.bootstrap') }}</div>
    @endif
  </div>

  <p class="text-muted mt-3 mb-0" style="font-size:11px">
    <i class="fa-solid fa-circle-info"></i>
    Tarif dihitung otomatis dari kartu harga server × spesifikasi VM. Potongan saldo dijalankan tiap jam lewat cron
    <code>lumora:charge-hourly-usage</code> — cek statusnya di Pengaturan → Cron Jobs.
  </p>

  <script>
    function genPass(fieldId) {
      const U = 'ABCDEFGHJKLMNPQRSTUVWXYZ', L = 'abcdefghijkmnpqrstuvwxyz', D = '23456789';
      const all = U + L + D;
      const pick = (s) => s[Math.floor(Math.random() * s.length)];
      let p = [pick(U), pick(L), pick(D)];
      for (let i = 0; i < 9; i++) p.push(pick(all));
      document.getElementById(fieldId).value = p.sort(() => Math.random() - 0.5).join('');
    }
  </script>

@endsection
