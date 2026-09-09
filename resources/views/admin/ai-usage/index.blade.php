@extends('layouts.admin')
@section('title', 'Trafik AI Chat Bot')

@section('content')
  @include('admin.settings._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Trafik AI Chat Bot</h1>
      <p class="small text-muted mb-0">Token dicatat apa adanya dari Anthropic — estimasi biaya memakai tarif yang kamu isi sendiri di bawah.</p>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
      <input type="date" name="from" value="{{ $mulai->format('Y-m-d') }}" class="form-control form-control-sm" style="width:9.5rem">
      <span class="text-muted" style="font-size:12px">s.d.</span>
      <input type="date" name="to" value="{{ $sampai->format('Y-m-d') }}" class="form-control form-control-sm" style="width:9.5rem">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Terapkan</button>
    </form>
  </div>

  <div class="rounded-3 px-3 py-2 mb-4" style="max-width:48rem;background:#fffbeb;border:1px solid #fde68a;font-size:12px;color:#92400e">
    <i class="fa-solid fa-circle-info"></i>
    Angka token di sini <b>akurat 100%</b> (langsung dari respons Anthropic). Estimasi biaya (Rupiah/USD)
    memakai tarif yang <b>kamu isi sendiri</b> di bawah — cek harga terkini di
    <a href="https://claude.com/pricing" target="_blank" class="text-decoration-underline" style="color:inherit">claude.com/pricing</a>
    sebelum mengisi, karena tarif bisa berubah sewaktu-waktu.
  </div>

  {{-- Ringkasan --}}
  <div class="row g-3 mb-4">
    @php
      $cards = [
        ['label' => 'Total Pesan Bot', 'value' => number_format($totalPesan), 'icon' => 'fa-comments', 'fg' => '#4f46e5'],
        ['label' => 'Token Masuk (Input)', 'value' => number_format($totalInput), 'icon' => 'fa-arrow-down', 'fg' => '#0891b2'],
        ['label' => 'Token Keluar (Output)', 'value' => number_format($totalOutput), 'icon' => 'fa-arrow-up', 'fg' => '#b45309'],
        ['label' => 'Estimasi Biaya', 'value' => '$' . number_format($estimasiTotal, 4), 'icon' => 'fa-coins', 'fg' => '#047857'],
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

  <div class="row g-4">
    {{-- Per model --}}
    <div class="col-12 col-lg-7">
      <div class="card border rounded-4 overflow-hidden">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-0">Per Model</h2>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" style="font-size:13px">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="px-4 py-2">Model</th>
                <th class="text-end py-2">Pesan</th>
                <th class="text-end py-2">Token In</th>
                <th class="text-end py-2">Token Out</th>
                <th class="text-end px-4 py-2">Estimasi</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($perModel as $row)
                @php
                  $t = $tarif[$row->model] ?? ['in' => 0, 'out' => 0];
                  $biaya = ($row->input_tokens / 1000000 * $t['in']) + ($row->output_tokens / 1000000 * $t['out']);
                @endphp
                <tr>
                  <td class="px-4 py-2 text-dark" style="font-family:monospace;font-size:11px">{{ $row->model }}</td>
                  <td class="text-end py-2 text-muted">{{ number_format($row->pesan) }}</td>
                  <td class="text-end py-2 text-muted">{{ number_format($row->input_tokens) }}</td>
                  <td class="text-end py-2 text-muted">{{ number_format($row->output_tokens) }}</td>
                  <td class="text-end px-4 py-2 fw-medium text-dark">${{ number_format($biaya, 4) }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pemakaian di rentang ini.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- Per hari --}}
      <div class="card border rounded-4 overflow-hidden mt-4">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-0">Per Hari (30 hari terakhir dalam rentang)</h2>
        </div>
        <div class="table-responsive" style="max-height:20rem;overflow-y:auto">
          <table class="table table-sm align-middle mb-0" style="font-size:13px">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc;position:sticky;top:0">
                <th class="px-4 py-2">Tanggal</th>
                <th class="text-end py-2">Pesan</th>
                <th class="text-end px-4 py-2">Total Token</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($perHari as $row)
                <tr>
                  <td class="px-4 py-2 text-dark">{{ \Carbon\Carbon::parse($row->tanggal)->translatedFormat('d M Y') }}</td>
                  <td class="text-end py-2 text-muted">{{ number_format($row->pesan) }}</td>
                  <td class="text-end px-4 py-2 text-muted">{{ number_format($row->input_tokens + $row->output_tokens) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted py-4">Belum ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Tarif --}}
    <div class="col-12 col-lg-5">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-1">Tarif untuk Estimasi</h2>
        <p class="text-muted mb-3" style="font-size:11px">USD per 1 juta token. Cek angka terkini di claude.com/pricing sebelum mengisi.</p>

        <form method="POST" action="{{ route('admin.ai-usage.pricing') }}">
          @csrf
          @foreach ([
            ['key' => 'haiku', 'label' => 'Claude Haiku 4.5'],
            ['key' => 'sonnet', 'label' => 'Claude Sonnet 5'],
            ['key' => 'opus', 'label' => 'Claude Opus 4.8'],
            ['key' => 'gpt4o_mini', 'label' => 'GPT-4o mini'],
            ['key' => 'gpt4o', 'label' => 'GPT-4o'],
          ] as $m)
            <div class="row g-2 mb-3 align-items-end">
              <div class="col-12">
                <label class="form-label small fw-medium text-dark mb-1">{{ $m['label'] }}</label>
              </div>
              <div class="col-6">
                <label class="text-muted mb-1 d-block" style="font-size:10px">Input ($/MTok)</label>
                <input type="number" step="0.01" min="0" name="ai_price_{{ $m['key'] }}_in"
                       value="{{ old('ai_price_' . $m['key'] . '_in', $tarifSimple[$m['key']]['in']) }}"
                       class="form-control form-control-sm">
              </div>
              <div class="col-6">
                <label class="text-muted mb-1 d-block" style="font-size:10px">Output ($/MTok)</label>
                <input type="number" step="0.01" min="0" name="ai_price_{{ $m['key'] }}_out"
                       value="{{ old('ai_price_' . $m['key'] . '_out', $tarifSimple[$m['key']]['out']) }}"
                       class="form-control form-control-sm">
              </div>
            </div>
          @endforeach
          <button type="submit" class="btn btn-primary btn-sm w-100">Simpan Tarif</button>
        </form>
      </div>
    </div>
  </div>
@endsection
