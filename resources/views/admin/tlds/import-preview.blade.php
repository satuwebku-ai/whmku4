@extends('layouts.admin')

@section('title', 'Pratinjau Impor Harga')

@section('content')

  @include('admin.domains._nav')

  <div class="mb-5">
    <a href="{{ route('admin.tlds.pricing', isset($registrarId) ? ['registrar' => $registrarId] : []) }}" class="text-xs text-slate-400 hover:text-slate-600">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke TLD Pricing
    </a>
    <h1 class="text-xl font-bold text-slate-800 mt-1">Pratinjau Impor Harga</h1>
    <p class="text-sm text-slate-500 mt-1">
      {{ count($rows) }} ekstensi terbaca{{ !empty($source) ? ' dari ' . $source : '' }}.
      Belum ada yang disimpan — periksa dan sesuaikan dulu, lalu klik Terapkan di bawah.
    </p>
  </div>

  <form method="POST" action="{{ route('admin.tld.import-apply') }}">
    @csrf
    {{-- Wajib diteruskan: tanpa ini importApply() tidak tahu TLD hasil
         impor ini milik registrar mana, dan semuanya akan jatuh ke
         "Manual (Tidak Ditentukan)". --}}
    <input type="hidden" name="registrar_id" value="{{ $registrarId ?? '' }}">

    {{-- Alat bantu massal --}}
    <div class="card p-4 mb-4 border-indigo-200 bg-indigo-50/40">
      <div class="grid sm:grid-cols-4 gap-3 items-end">
        <div>
          <label class="form-label">Ubah Markup Semua Baris (%)</label>
          <input type="number" step="0.1" min="0" id="massMarkup" value="{{ $markup }}" class="form-input">
        </div>
        <div>
          <label class="form-label">Pembulatan (Rp)</label>
          <select id="massRound" class="form-input">
            <option value="0" @selected($roundTo === 0)>Tanpa pembulatan</option>
            <option value="1000" @selected($roundTo === 1000)>Ribuan terdekat</option>
            <option value="5000" @selected($roundTo === 5000)>5 ribu terdekat</option>
            <option value="10000" @selected($roundTo === 10000)>10 ribu terdekat</option>
          </select>
        </div>
        <button type="button" id="applyMarkup" class="btn btn-outline">
          <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Hitung Ulang Harga Jual
        </button>
        <div class="flex items-center gap-4 text-sm text-slate-600">
          <label class="flex items-center gap-2">
            <input type="checkbox" id="checkAllInclude" checked class="rounded border-slate-300 text-accent focus:ring-accent/40">
            Pilih semua
          </label>
          <label class="flex items-center gap-2">
            <input type="checkbox" id="checkAllActive" class="rounded border-slate-300 text-accent focus:ring-accent/40">
            Aktifkan semua
          </label>
        </div>
      </div>
      <p class="text-[11px] text-slate-500 mt-2">
        "Hitung Ulang" menimpa kolom Harga Jual di semua baris. Kalau ada baris yang sudah kamu
        atur sendiri, aturlah setelah menekan tombol ini.
      </p>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
              <th class="px-4 py-2.5 font-semibold text-center">Impor</th>
              <th class="px-4 py-2.5 font-semibold">Ekstensi</th>
              <th class="px-3 py-2.5 font-semibold">Status</th>
              <th class="px-3 py-2.5 font-semibold text-right">Modal (Rp)</th>
              <th class="px-3 py-2.5 font-semibold text-right">Harga Jual (Rp)</th>
              <th class="px-3 py-2.5 font-semibold text-right">Margin</th>
              <th class="px-3 py-2.5 font-semibold text-center">Aktifkan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @foreach ($rows as $i => $row)
              <tr class="hover:bg-slate-50/60" data-row>
                <td class="px-4 py-2 text-center">
                  <input type="checkbox" name="include[]" value="{{ $i }}" checked
                         data-include class="rounded border-slate-300 text-accent focus:ring-accent/40">
                </td>

                <td class="px-4 py-2 font-medium text-slate-700 whitespace-nowrap">
                  {{ $row['extension'] }}
                  <input type="hidden" name="rows[{{ $i }}][extension]" value="{{ $row['extension'] }}">
                </td>

                <td class="px-3 py-2">
                  @if ($row['exists'])
                    <span class="badge badge-inactive">Sudah ada</span>
                    @if ($row['old_price'] > 0)
                      <span class="block text-[10px] text-slate-400 mt-0.5">
                        jual lama: Rp {{ number_format($row['old_price'], 0, ',', '.') }}
                      </span>
                    @endif
                  @else
                    <span class="badge badge-active">Baru</span>
                  @endif
                </td>

                <td class="px-3 py-2">
                  <input type="number" step="1" min="0" data-cost
                         name="rows[{{ $i }}][cost]" value="{{ (int) $row['cost'] }}"
                         class="w-32 px-2 py-1.5 rounded-lg border border-slate-200 text-right text-sm outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </td>

                <td class="px-3 py-2">
                  <input type="number" step="1" min="0" data-selling
                         name="rows[{{ $i }}][selling]" value="{{ (int) $row['selling'] }}"
                         class="w-32 px-2 py-1.5 rounded-lg border border-slate-200 text-right text-sm font-medium outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </td>

                <td class="px-3 py-2 text-right text-xs whitespace-nowrap" data-margin>—</td>

                <td class="px-3 py-2 text-center">
                  <input type="checkbox" name="active[]" value="{{ $i }}" @checked($row['active'])
                         data-active class="rounded border-slate-300 text-accent focus:ring-accent/40">
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap bg-slate-50/60">
        <p class="text-xs text-slate-500">
          Hanya baris yang dicentang di kolom <b>Impor</b> yang akan disimpan.
          TLD dengan harga jual 0 tidak akan diaktifkan meski dicentang.
        </p>
        <div class="flex items-center gap-2">
          <a href="{{ route('admin.tlds.pricing', isset($registrarId) ? ['registrar' => $registrarId] : []) }}" class="btn btn-outline">Batal</a>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check text-xs"></i> Terapkan ke TLD Pricing
          </button>
        </div>
      </div>
    </div>
  </form>

  <script>
    (function () {
      const rupiah = new Intl.NumberFormat('id-ID');
      const rows = Array.from(document.querySelectorAll('[data-row]'));

      function updateMargin(row) {
        const cost = parseFloat(row.querySelector('[data-cost]').value) || 0;
        const sell = parseFloat(row.querySelector('[data-selling]').value) || 0;
        const cell = row.querySelector('[data-margin]');

        if (cost <= 0 || sell <= 0) {
          cell.innerHTML = '<span class="text-slate-300">—</span>';
          return;
        }

        const margin = sell - cost;
        const percent = (margin / cost * 100).toFixed(1);
        const tone = margin > 0 ? 'text-emerald-600' : 'text-rose-600';

        cell.innerHTML = '<span class="' + tone + '">Rp ' + rupiah.format(Math.round(margin)) +
                         '<br><span class="text-slate-400">' + percent + '%</span></span>';
      }

      // Hitung ulang harga jual semua baris dari markup yang diketik.
      document.getElementById('applyMarkup').addEventListener('click', function () {
        const markup = parseFloat(document.getElementById('massMarkup').value) || 0;
        const round = parseInt(document.getElementById('massRound').value, 10) || 0;

        rows.forEach(function (row) {
          const cost = parseFloat(row.querySelector('[data-cost]').value) || 0;

          if (cost <= 0) return;

          let sell = cost * (1 + markup / 100);
          if (round > 0) sell = Math.ceil(sell / round) * round;

          row.querySelector('[data-selling]').value = Math.round(sell);
          updateMargin(row);
        });
      });

      function toggleAll(sourceId, selector) {
        document.getElementById(sourceId).addEventListener('change', function () {
          const on = this.checked;
          rows.forEach(function (row) { row.querySelector(selector).checked = on; });
        });
      }

      toggleAll('checkAllInclude', '[data-include]');
      toggleAll('checkAllActive', '[data-active]');

      rows.forEach(function (row) {
        row.querySelectorAll('[data-cost], [data-selling]').forEach(function (input) {
          input.addEventListener('input', function () { updateMargin(row); });
        });
        updateMargin(row);
      });
    })();
  </script>

@endsection
