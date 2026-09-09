@extends('layouts.admin')

@section('title', 'Cron Jobs')

@section('content')

  @include('admin.settings._nav')

  @php use App\Models\Setting; use App\Models\CronJob; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Cron Jobs</h1>
    <p class="small text-muted mb-0">Tugas otomatis yang berjalan di latar belakang: pengingat tagihan, penandaan tunggakan, dan pembersihan data.</p>
  </div>

  @if ($neverRan)
    <div class="card border rounded-4 p-4 mb-4" style="background:#fffbeb;border-color:#fde68a!important">
      <p class="small fw-bold mb-1" style="color:#92400e">
        <i class="fa-solid fa-triangle-exclamation"></i> Cron server belum berjalan
      </p>
      <p class="mb-0" style="font-size:12px;color:#b45309">
        Belum ada satu pun tugas yang pernah dijalankan. Selama cron di server belum dipasang,
        pengingat tagihan dan tugas lain tidak akan jalan — pasang lewat panel di bawah.
      </p>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-12 col-lg-8">

      {{-- Daftar tugas --}}
      <form method="POST" action="{{ route('admin.cron.update') }}">
        @csrf

        <div class="card border rounded-4 overflow-hidden mb-3">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                  <th class="px-3 py-3">Tugas</th>
                  <th class="py-3">Jadwal</th>
                  <th class="py-3">Terakhir Jalan</th>
                  <th class="py-3">Status</th>
                  <th class="text-center py-3">Aktif</th>
                  <th class="text-end px-3 py-3">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @php
                  $jobBadge = ['active' => 'badge-soft-success', 'suspended' => 'badge-soft-danger', 'pending' => 'badge-soft-warning', 'inactive' => 'badge-soft-secondary'];
                @endphp
                @forelse ($jobs as $job)
                  <tr>
                    <td class="px-3 py-3">
                      <p class="fw-medium text-dark mb-0">{{ $job->name }}</p>
                      <p class="text-muted mb-0" style="font-size:11px;font-family:monospace">{{ $job->command }}</p>
                      @if ($job->description)
                        <p class="text-muted mb-0 mt-1" style="font-size:11px">{{ $job->description }}</p>
                      @endif
                    </td>

                    <td class="py-3">
                      <select name="jobs[{{ $job->id }}][interval_minutes]" class="form-select" style="padding:.25rem .5rem;font-size:.75rem;border-radius:.375rem">
                        @foreach (CronJob::INTERVALS as $menit => $label)
                          <option value="{{ $menit }}" @selected($job->interval_minutes === $menit)>{{ $label }}</option>
                        @endforeach
                      </select>
                      @if ($job->next_run_at && $job->is_enabled)
                        <p class="text-muted mt-1 mb-0" style="font-size:10px">
                          Berikutnya {{ $job->next_run_at->diffForHumans() }}
                        </p>
                      @endif
                    </td>

                    <td class="text-muted py-3" style="font-size:12px">
                      @if ($job->last_run_at)
                        {{ $job->last_run_at->diffForHumans() }}
                        <span class="d-block text-muted" style="font-size:10px">
                          {{ $job->run_count }}x · {{ $job->last_duration_ms }}ms
                        </span>
                      @else
                        <span class="text-muted">Belum pernah</span>
                      @endif
                    </td>

                    <td class="py-3">
                      <span class="badge {{ $jobBadge[$job->status_badge] ?? 'badge-soft-secondary' }}">
                        {{ $job->last_status ? ucfirst($job->last_status) : 'Menunggu' }}
                      </span>
                      @if ($job->last_output)
                        <p class="text-muted text-truncate mt-1 mb-0" style="font-size:10px;max-width:180px" title="{{ $job->last_output }}">
                          {{ $job->last_output }}
                        </p>
                      @endif
                    </td>

                    <td class="text-center py-3">
                      <input type="checkbox" name="enabled[]" value="{{ $job->id }}" @checked($job->is_enabled) class="form-check-input">
                    </td>

                    <td class="text-end px-3 py-3">
                      <button type="submit" form="run-{{ $job->id }}"
                              class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0"
                              title="Jalankan sekarang">
                        <i class="fa-solid fa-play" style="font-size:12px"></i>
                      </button>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-5">Belum ada tugas terdaftar.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:#f8fafc">
            <p class="text-muted mb-0" style="font-size:12px">Ubah jadwal atau matikan tugas, lalu simpan.</p>
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Jadwal
            </button>
          </div>
        </div>
      </form>

      {{-- Form "jalankan sekarang" diletakkan di luar tabel: HTML tidak
           mengizinkan form bersarang. --}}
      @foreach ($jobs as $job)
        <form id="run-{{ $job->id }}" method="POST" action="{{ route('admin.cron.run', $job) }}" class="d-none">
          @csrf
        </form>
      @endforeach
    </div>

    {{-- Sidebar: pemasangan cron --}}
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 mb-3">
        <h2 class="small fw-bold text-dark mb-1">Pasang Cron di Server</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Cukup <b>satu baris cron</b> untuk semua tugas di atas. Jadwal tiap tugas diatur dari halaman ini,
          bukan dari baris cron-nya.
        </p>

        <label class="form-label small fw-medium text-dark">Perintah</label>
        <div class="d-flex align-items-center gap-2 mb-2">
          <input type="text" readonly value="{{ $cronLine }}" id="cronLine"
                 class="form-control form-control-sm" style="font-size:11px;font-family:monospace">
          <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronLine').value); this.innerHTML='<i class=\'fa-solid fa-check\' style=\'font-size:11px\'></i>'"
                  class="btn btn-outline-secondary btn-sm flex-shrink-0" title="Salin">
            <i class="fa-regular fa-copy" style="font-size:11px"></i>
          </button>
        </div>
        <p class="text-muted mb-3" style="font-size:11px">
          Pasang di cPanel → Cron Jobs dengan setelan <b>Once Per Minute</b> (<code>* * * * *</code>).
        </p>

        {{-- Panduan langkah demi langkah, disembunyikan agar tidak
             memenuhi halaman bagi yang sudah paham. --}}
        <details class="rounded-3 border" style="background:#f8fafc">
          <summary class="px-3 py-2 small fw-medium text-dark" style="cursor:pointer;user-select:none">
            <i class="fa-solid fa-circle-question"></i> Panduan lengkap memasang di cPanel
          </summary>

          <div class="px-3 pb-3 pt-1 text-muted" style="font-size:12px">
            <p class="mb-2"><b>1.</b> Login ke cPanel, cari menu <b>Cron Jobs</b> (biasanya di bagian Advanced).</p>

            <p class="mb-2"><b>2.</b> Di bagian <b>Add New Cron Job</b>, pada dropdown <b>Common Settings</b>
               pilih <b>Once Per Minute (* * * * *)</b>. Kelima kolom di bawahnya akan terisi
               tanda bintang otomatis.</p>

            <p class="mb-2"><b>3.</b> Di kolom <b>Command</b>, tempel perintah yang sudah disalin di atas.</p>

            <p class="mb-2"><b>4.</b> Klik <b>Add New Cron Job</b>. Selesai.</p>

            <div class="rounded-2 bg-white border p-2 mb-2">
              <p class="fw-bold text-dark mb-1">Kenapa tiap menit?</p>
              <p class="mb-0" style="line-height:1.6">
                Laravel sendiri yang menentukan kapan tiap tugas benar-benar dijalankan —
                cron hanya bertugas "membangunkan" Laravel setiap menit untuk memeriksa
                ada tugas yang jatuh tempo atau tidak. Jadi meski cron jalan tiap menit,
                pengingat tagihan tetap terkirim sekali sehari sesuai jadwal di halaman ini.
              </p>
            </div>

            <div class="rounded-2 bg-white border p-2 mb-2">
              <p class="fw-bold text-dark mb-1">Kalau muncul email tiap menit</p>
              <p class="mb-0" style="line-height:1.6">
                Kosongkan kolom <b>Email</b> di halaman Cron Jobs cPanel, atau pastikan
                perintahnya diakhiri <code>&gt;&gt; /dev/null 2&gt;&amp;1</code> seperti pada
                perintah di atas — itu yang membuat keluarannya tidak dikirim sebagai email.
              </p>
            </div>

            <div class="rounded-2 bg-white border p-2">
              <p class="fw-bold text-dark mb-1">Kalau tetap tidak jalan</p>
              <p class="mb-0" style="line-height:1.6">
                Sebagian hosting memakai path PHP berbeda. Coba ganti <code>php</code> di awal
                perintah dengan path lengkap, misalnya <code>/usr/local/bin/php</code> atau
                <code>/opt/alt/php82/usr/bin/php</code>. Path yang benar bisa ditanyakan ke
                penyedia hosting, atau dilihat di cPanel → Select PHP Version.
              </p>
            </div>
          </div>
        </details>
      </div>

      {{-- Integrasi cPanel --}}
      <form method="POST" action="{{ route('admin.cron.settings') }}" class="card border rounded-4 p-4 mb-3">
        @csrf
        <h2 class="small fw-bold text-dark mb-2">Pasang Otomatis ke cPanel</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Isi kredensial cPanel <b>tempat aplikasi ini di-hosting</b>, lalu cron bisa dipasang
          tanpa membuka cPanel. Berbeda dari kredensial server pelanggan di menu Server.
        </p>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Host cPanel</label>
          <input type="text" name="cpanel_host" value="{{ Setting::get('cpanel_host') }}" class="form-control form-control-sm" placeholder="beragam.kreasi.org">
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-medium text-dark">Port</label>
            <input type="number" name="cpanel_port" value="{{ Setting::get('cpanel_port', 2083) }}" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-medium text-dark">Username</label>
            <input type="text" name="cpanel_user" value="{{ Setting::get('cpanel_user') }}" class="form-control form-control-sm" placeholder="appskumy">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">API Token {{ Setting::get('cpanel_token') ? '(kosongkan jika tidak diganti)' : '' }}</label>
          <input type="password" name="cpanel_token" class="form-control form-control-sm" placeholder="{{ Setting::get('cpanel_token') ? '••••••••••••' : 'Token dari cPanel → Manage API Tokens' }}">
        </div>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Path PHP <span class="text-muted fw-normal">(opsional)</span></label>
          <input type="text" name="cpanel_php_path" value="{{ Setting::get('cpanel_php_path') }}" class="form-control form-control-sm" placeholder="/usr/local/bin/php">
          <p class="text-muted mt-1 mb-0" style="font-size:11px">Isi kalau versi PHP default server berbeda dengan yang dipakai aplikasi.</p>
        </div>

        <label class="d-flex align-items-center gap-2 small text-dark mb-3">
          <input type="checkbox" name="cpanel_verify_ssl" value="1" @checked(Setting::get('cpanel_verify_ssl', '1') === '1')
                 class="form-check-input" style="margin-top:0">
          Verifikasi SSL
        </label>

        <div class="pt-3 border-top d-flex flex-column gap-2">
          <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Kredensial</button>
          <button type="submit" formaction="{{ route('admin.cron.test-cpanel') }}" class="btn btn-outline-secondary btn-sm w-100">
            <i class="fa-solid fa-plug" style="font-size:11px"></i> Tes Koneksi
          </button>
          <button type="submit" formaction="{{ route('admin.cron.install-cpanel') }}" class="btn btn-outline-secondary btn-sm w-100"
                  {{ $cpanelConfigured ? '' : 'disabled' }}>
            <i class="fa-solid fa-download" style="font-size:11px"></i> Pasang Cron Sekarang
          </button>
        </div>

        @unless ($cpanelConfigured)
          <p class="text-warning mt-2 mb-0" style="font-size:11px">Simpan kredensial dulu sebelum memasang cron otomatis.</p>
        @endunless
      </form>

      {{-- Auto suspend --}}
      <form method="POST" action="{{ route('admin.cron.settings') }}" class="card border rounded-4 p-4">
        @csrf
        <h2 class="small fw-bold text-dark mb-2">Suspend Otomatis</h2>

        <label class="d-flex align-items-start gap-2 small text-dark mb-3">
          <input type="checkbox" name="auto_suspend" value="1" @checked(Setting::get('auto_suspend', '0') === '1')
                 class="form-check-input flex-shrink-0" style="margin-top:2px">
          <span>
            <span class="d-block fw-medium text-dark">Aktifkan suspend otomatis</span>
            <span class="d-block text-muted" style="font-size:11px">Layanan disuspend kalau tagihan menunggak melewati batas.</span>
          </span>
        </label>

        <div class="mb-3">
          <label class="form-label small fw-medium text-dark">Toleransi (hari setelah jatuh tempo)</label>
          <input type="number" name="suspend_grace_days" value="{{ Setting::get('suspend_grace_days', 7) }}" min="1" max="90" class="form-control form-control-sm">
        </div>

        <div class="rounded-3 px-3 py-2 mb-3" style="background:#fffbeb;border:1px solid #fde68a;font-size:11px;color:#92400e">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Suspend bisa dibatalkan, tapi tetap membuat website pelanggan mati.
          Pastikan pengingat tagihan sudah berjalan lebih dulu sebelum mengaktifkan ini.
        </div>

        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      </form>
    </div>
  </div>

@endsection
