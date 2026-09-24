@extends('layouts.admin')

@section('title', 'Pulihkan Sebagian')

@section('content')

  @php
    $selectedTables = old('tables', []);
    $selectedMode   = old('mode', 'missing');
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Pulihkan Sebagian</h1>
      <p class="small text-muted mb-0">
        Pilih tabel mana saja yang dimasukkan kembali dari cadangan <b class="text-dark">{{ $sourceLabel }}</b>.
        Tabel yang tidak dipilih tidak disentuh sama sekali. File upload (bukti bayar, dokumen) tidak ikut dipulihkan.
      </p>
    </div>
    <a href="{{ route('admin.backups.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-left" style="font-size:11px"></i> Kembali
    </a>
  </div>

  <form method="POST" action="{{ $action }}" id="selectiveForm"
        data-confirm="Pulihkan tabel terpilih dari cadangan? Cadangan pengaman dibuat otomatis dulu." data-confirm-title="Pulihkan Sebagian" data-confirm-style="warn" data-confirm-label="Ya, Pulihkan">
    @csrf

    {{-- ── 1. Cara memasukkan data ── --}}
    <div class="card border rounded-4 p-4 mb-3">
      <h2 class="small fw-bold text-dark mb-3">1. Cara memasukkan data</h2>

      <div class="row g-2">
        <div class="col-12 col-lg-4">
          <label class="d-block border rounded-3 p-3 h-100" style="cursor:pointer">
            <input type="radio" name="mode" value="missing" class="form-check-input me-1" @checked($selectedMode === 'missing')>
            <span class="small fw-bold text-dark">Tambah yang hilang saja</span>
            <span class="badge bg-success-subtle text-success ms-1" style="font-size:10px">Paling aman</span>
            <span class="d-block text-muted mt-1" style="font-size:12px">
              Baris yang sudah ada sekarang <b>tidak diubah</b>. Hanya baris yang tidak ada lagi (mis. terhapus tak sengaja) yang dimasukkan kembali.
            </span>
          </label>
        </div>
        <div class="col-12 col-lg-4">
          <label class="d-block border rounded-3 p-3 h-100" style="cursor:pointer">
            <input type="radio" name="mode" value="upsert" class="form-check-input me-1" @checked($selectedMode === 'upsert')>
            <span class="small fw-bold text-dark">Kembalikan ke isi cadangan</span>
            <span class="d-block text-muted mt-1" style="font-size:12px">
              Baris yang hilang dimasukkan, dan baris yang berubah <b>ditimpa</b> isi cadangan. Baris baru (dibuat setelah cadangan) tetap dibiarkan.
            </span>
          </label>
        </div>
        <div class="col-12 col-lg-4">
          <label class="d-block border rounded-3 p-3 h-100" style="cursor:pointer;border-color:#fecaca!important">
            <input type="radio" name="mode" value="replace" class="form-check-input me-1" @checked($selectedMode === 'replace')>
            <span class="small fw-bold" style="color:#991b1b">Timpa tabel</span>
            <span class="d-block mt-1" style="font-size:12px;color:#991b1b">
              Tabel dihapus lalu dibuat ulang persis seperti cadangan (struktur + isi). Baris baru di tabel itu <b>ikut hilang</b>.
            </span>
          </label>
        </div>
      </div>

      @error('mode') <p class="text-danger mt-2 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    {{-- ── 2. Pilih tabel ── --}}
    <div class="card border rounded-4 overflow-hidden mb-3">
      <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h2 class="small fw-bold text-dark mb-0">2. Pilih tabel <span class="text-muted fw-normal">(<span id="selCount">0</span> dipilih)</span></h2>
        <div class="d-flex align-items-center gap-2">
          <input type="search" id="tableSearch" class="form-control form-control-sm" style="width:200px" placeholder="Cari tabel...">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="selAll">Pilih semua</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="selNone">Kosongkan</button>
        </div>
      </div>

      <div class="table-responsive" style="max-height:520px;overflow-y:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="small text-muted" style="position:sticky;top:0;background:#fff;z-index:1">
            <tr>
              <th style="width:44px"></th>
              <th>Tabel</th>
              <th class="text-end">Baris di cadangan</th>
              <th class="text-end">Baris sekarang</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($tables as $t)
              <tr data-table="{{ $t['table'] }}" data-mergeable="{{ empty($t['merge_blockers']) ? 1 : 0 }}">
                <td class="ps-3">
                  <input type="checkbox" name="tables[]" value="{{ $t['table'] }}" class="form-check-input"
                         @checked(in_array($t['table'], $selectedTables, true))>
                </td>
                <td class="small"><code class="text-dark">{{ $t['table'] }}</code></td>
                <td class="small text-end">{{ number_format($t['backup_rows']) }}</td>
                <td class="small text-end">{{ $t['current_rows'] === null ? '—' : number_format($t['current_rows']) }}</td>
                <td style="font-size:11px">
                  @if (! $t['exists'])
                    <span class="badge bg-warning-subtle text-warning">Tidak ada di database sekarang</span>
                  @elseif ($t['structure_differs'])
                    <span class="badge bg-warning-subtle text-warning" title="Kolom tabel sekarang berbeda dari cadangan (ada perubahan struktur setelah cadangan dibuat).">Struktur berbeda</span>
                  @endif
                  @if ($t['backup_rows'] === 0)
                    <span class="badge bg-secondary-subtle text-secondary">Kosong di cadangan</span>
                  @endif
                  @if (! empty($t['merge_blockers']))
                    <span class="js-blocker text-danger d-none">Hanya bisa dengan "Timpa tabel": {{ implode('; ', $t['merge_blockers']) }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @error('tables') <p class="text-danger px-4 py-2 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      @error('tables.*') <p class="text-danger px-4 py-2 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="card border rounded-4 p-4 mb-3" style="background:#fffbeb;border-color:#fde68a!important">
      <p class="mb-0" style="font-size:12px;color:#92400e">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Cadangan pengaman dari keadaan sekarang dibuat otomatis dulu sebelum apa pun diubah. Pemulihan dilakukan tanpa
        memeriksa relasi antar tabel (foreign key), jadi pulihkan tabel yang saling berkaitan <b>bersama-sama</b>
        (mis. invoice beserta item-nya) supaya tidak ada data yatim.
      </p>
    </div>

    <button type="submit" id="selSubmit" class="btn btn-primary" disabled>
      <i class="fa-solid fa-clock-rotate-left" style="font-size:12px"></i> Pulihkan Tabel Terpilih
    </button>
  </form>

  <script>
    (function () {
      const form   = document.getElementById('selectiveForm');
      const rows   = Array.from(form.querySelectorAll('tr[data-table]'));
      const counter = document.getElementById('selCount');
      const submit = document.getElementById('selSubmit');
      const search = document.getElementById('tableSearch');

      const modes = {
        missing: {
          title: 'Tambah yang Hilang', style: 'info', label: 'Ya, Tambahkan',
          text: 'Masukkan kembali baris yang hilang dari {n} tabel terpilih? Baris yang sudah ada sekarang tidak diubah.',
        },
        upsert: {
          title: 'Kembalikan ke Isi Cadangan', style: 'warn', label: 'Ya, Kembalikan',
          text: 'Kembalikan {n} tabel terpilih ke isi cadangan? Baris yang berubah sejak cadangan dibuat AKAN DITIMPA isi cadangan. Baris baru dibiarkan.',
        },
        replace: {
          title: 'Timpa Tabel', style: 'danger', label: 'Ya, Timpa Tabel',
          text: 'TIMPA {n} tabel terpilih? Tiap tabel dihapus lalu dibuat ulang dari cadangan — baris yang dibuat setelah cadangan HILANG dari tabel itu. Tabel lain tidak disentuh.',
        },
      };

      const currentMode = () => form.querySelector('input[name="mode"]:checked')?.value || 'missing';
      const box = (tr) => tr.querySelector('input[type="checkbox"]');

      function refresh() {
        const n = rows.filter((tr) => box(tr).checked).length;
        const m = modes[currentMode()];

        counter.textContent = n;
        submit.disabled = n === 0;
        submit.className = 'btn ' + (currentMode() === 'replace' ? 'btn-danger' : 'btn-primary');

        form.dataset.confirm = m.text.replace('{n}', n);
        form.dataset.confirmTitle = m.title;
        form.dataset.confirmStyle = m.style;
        form.dataset.confirmLabel = m.label;
      }

      function applyMode() {
        const merge = currentMode() !== 'replace';

        rows.forEach((tr) => {
          const blocked = merge && tr.dataset.mergeable === '0';
          const b = box(tr);
          const note = tr.querySelector('.js-blocker');

          b.disabled = blocked;
          if (blocked) b.checked = false;
          tr.classList.toggle('text-muted', blocked);
          if (note) note.classList.toggle('d-none', !blocked);
        });

        refresh();
      }

      const selectable = () => rows.filter((tr) => tr.style.display !== 'none' && !box(tr).disabled);

      document.getElementById('selAll').addEventListener('click', () => { selectable().forEach((tr) => (box(tr).checked = true)); refresh(); });
      document.getElementById('selNone').addEventListener('click', () => { rows.forEach((tr) => (box(tr).checked = false)); refresh(); });

      search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        rows.forEach((tr) => { tr.style.display = tr.dataset.table.toLowerCase().includes(q) ? '' : 'none'; });
      });

      form.addEventListener('change', (e) => {
        if (e.target.name === 'mode') applyMode(); else refresh();
      });

      applyMode();
    })();
  </script>

@endsection
