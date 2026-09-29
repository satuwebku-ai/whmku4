{{-- ══════════ Modal Checklist Setup ══════════
     Muncul SEKALI setiap kali admin login (flag session dibersihkan saat
     logout, lihat LogoutController), dan hanya kalau masih ada item yang
     belum jalan. Kalau semua sudah tercentang (atau dilewati), modal ini
     tidak dirender sama sekali.

     Dibuka paksa lewat:  ?setup=1  pada URL admin mana pun, atau
     otomatis setelah tombol "Lewati" ditekan (flash setup_reopen). --}}
@php
  $__setupAdmin = auth('admin')->user();
  $__setupForce = session('setup_reopen') || request()->boolean('setup');
  $__setup = null;

  if ($__setupAdmin && $__setupAdmin->hasModule('system')
      && ($__setupForce || ! session()->has('setup_checklist_shown'))) {
      // Ditandai lebih dulu supaya halaman berikutnya di sesi yang sama
      // tidak menghitung ulang (pengecekan menyentuh database & disk).
      session()->put('setup_checklist_shown', true);

      try {
          $__setup = app(\App\Services\SetupChecklistService::class)->summary();
      } catch (\Throwable $e) {
          report($e);
      }
  }
@endphp

@if ($__setup && $__setup['pending'] > 0)
  @php
    $__pendingItems = array_values(array_filter($__setup['items'], fn ($i) => ! $i['complete']));
    $__doneItems = array_values(array_filter($__setup['items'], fn ($i) => $i['complete']));
  @endphp

  <div class="modal" id="setupChecklistModal" tabindex="-1" aria-labelledby="setupChecklistTitle">
    <div class="modal-dialog modal-dialog-centered" style="max-width:640px">
      <div class="modal-content rounded-4 overflow-hidden">

        <div class="px-4" style="padding-top:1.5rem; padding-bottom:1rem">
          <div class="d-flex align-items-start gap-3">
            <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-warning bg-opacity-10 text-warning" style="width:44px;height:44px">
              <i class="fa-solid fa-list-check"></i>
            </span>
            <div class="flex-grow-1 min-w-0">
              <h3 id="setupChecklistTitle" class="h6 fw-bold text-dark mb-1">Ada {{ $__setup['pending'] }} hal yang belum siap di aplikasi</h3>
              <p class="small text-muted mb-0" style="line-height:1.6">
                Selesaikan daftar di bawah supaya semua fitur berjalan. Item akan tercentang otomatis begitu masalahnya teratasi, dan modal ini berhenti muncul kalau semuanya sudah beres.
              </p>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 mt-3">
            <div class="progress flex-grow-1" style="height:8px">
              <div class="progress-bar bg-success" style="width:{{ $__setup['percent'] }}%"></div>
            </div>
            <span class="small text-muted fw-semibold">{{ $__setup['done'] }}/{{ $__setup['total'] }}</span>
          </div>
        </div>

        <div class="px-4" style="max-height:55vh; overflow-y:auto; padding-bottom:.75rem">
          {{-- Belum selesai --}}
          @foreach ($__pendingItems as $item)
            <div class="d-flex align-items-start gap-3 py-3 border-top">
              <span class="flex-shrink-0 text-danger" style="width:22px; text-align:center; margin-top:2px" title="Belum selesai">
                <i class="fa-regular fa-square"></i>
              </span>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-dark small">{{ $item['title'] }}</div>
                <div class="small text-muted" style="line-height:1.5">{{ $item['description'] }}</div>
                @if ($item['detail'])
                  <div class="small text-danger mt-1" style="line-height:1.5">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>{{ $item['detail'] }}
                  </div>
                @endif
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                  @if ($item['url'] && $__setupAdmin->hasModule($item['module']))
                    <a href="{{ $item['url'] }}" class="btn btn-sm btn-primary">Buka pengaturan</a>
                  @endif
                  @if ($item['skippable'])
                    <form method="POST" action="{{ route('admin.setup-checklist.skip') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="key" value="{{ $item['key'] }}">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="Tandai tidak dipakai di situs ini">Lewati, tidak dipakai</button>
                    </form>
                  @endif
                </div>
              </div>
            </div>
          @endforeach

          {{-- Sudah selesai / dilewati --}}
          @foreach ($__doneItems as $item)
            <div class="d-flex align-items-start gap-3 py-2 border-top">
              <span class="flex-shrink-0 text-success" style="width:22px; text-align:center; margin-top:1px" title="Selesai">
                <i class="fa-solid fa-square-check"></i>
              </span>
              <div class="flex-grow-1 min-w-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="small text-muted">
                  {{ $item['title'] }}
                  @if ($item['skipped'])
                    <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Dilewati</span>
                  @endif
                </span>
                @if ($item['skipped'])
                  <form method="POST" action="{{ route('admin.setup-checklist.skip') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="key" value="{{ $item['key'] }}">
                    <input type="hidden" name="restore" value="1">
                    <button type="submit" class="btn btn-link btn-sm p-0">Batalkan</button>
                  </form>
                @endif
              </div>
            </div>
          @endforeach
        </div>

        <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-between gap-2">
          <a href="{{ request()->fullUrlWithQuery(['setup' => 1]) }}" class="small text-muted text-decoration-none">
            <i class="fa-solid fa-rotate me-1"></i>Periksa ulang
          </a>
          <button type="button" id="setupChecklistClose" class="btn btn-outline-secondary">Nanti saja</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.getElementById('setupChecklistModal');
      const closeBtn = document.getElementById('setupChecklistClose');
      let backdrop = null;

      function open() {
        modal.classList.add('show');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');

        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        document.body.appendChild(backdrop);
        requestAnimationFrame(() => backdrop.classList.add('show'));
      }

      function close() {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');

        if (backdrop) {
          backdrop.remove();
          backdrop = null;
        }
      }

      closeBtn.addEventListener('click', close);
      modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('show')) close(); });

      // Buang ?setup=1 dari address bar supaya refresh tidak membukanya lagi.
      if (window.history.replaceState && /[?&]setup=1/.test(window.location.search)) {
        const url = new URL(window.location.href);
        url.searchParams.delete('setup');
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
      }

      open();
    })();
  </script>
@endif
