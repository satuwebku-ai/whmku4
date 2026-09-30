<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  /* DomPDF hanya mendukung sebagian CSS — dijaga sederhana dan pakai
     inline-safe properties supaya render-nya konsisten. */
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; margin: 0; }
  .topbar { background: #4f46e5; height: 6px; width: 100%; margin-bottom: 26px; }
  .header { display: table; width: 100%; margin-bottom: 30px; }
  .header .col { display: table-cell; vertical-align: top; }
  .header .right { text-align: right; }
  .brand { font-size: 20px; font-weight: bold; color: #4f46e5; }
  .muted { color: #64748b; }
  .title { font-size: 24px; font-weight: bold; margin: 0 0 4px; color: #0f172a; }
  .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: bold; }
  .badge-paid    { background: #d1fae5; color: #047857; }
  .badge-unpaid  { background: #fef3c7; color: #b45309; }
  .badge-overdue { background: #fee2e2; color: #b91c1c; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
  table.items th { text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 8px 6px; }
  table.items td { padding: 10px 6px; border-bottom: 1px solid #f1f5f9; }
  table.items .amount { text-align: right; }
  .totals { width: 260px; margin-left: auto; margin-top: 10px; }
  .totals table { width: 100%; }
  .totals td { padding: 4px 0; }
  .totals .grand td { font-size: 15px; font-weight: bold; color: #4f46e5; border-top: 2px solid #1e293b; padding-top: 8px; }
  .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; }
</style>
</head>
<body>

  <div class="topbar"></div>

  <div class="header">
    <div class="col">
      @php $pdfLogo = \App\Models\Setting::get('site_logo'); @endphp
      @if ($pdfLogo)
        {{-- DomPDF butuh path file FISIK (bukan URL/rute) untuk gambar --
             disk 'local' dibaca langsung dari server, tidak lewat HTTP. --}}
        @php $logoPath = \Illuminate\Support\Facades\Storage::disk('local')->path('branding/' . $pdfLogo); @endphp
        @if (file_exists($logoPath))
          <img src="{{ $logoPath }}" style="max-height:50px;max-width:220px;margin-bottom:6px;" alt="{{ config('app.name') }}">
        @else
          <div class="brand">{{ config('app.name') }}</div>
        @endif
      @else
        <div class="brand">{{ config('app.name') }}</div>
      @endif
      <p class="muted" style="margin:4px 0 0">
        {{ \App\Models\Setting::get('company_address', '') }}
      </p>
    </div>
    <div class="col right">
      <p class="title">INVOICE</p>
      <p class="muted" style="margin:0">{{ $invoice->invoice_number }}</p>
      <p style="margin-top:8px">
        <span class="badge badge-{{ $invoice->is_overdue ? 'overdue' : $invoice->status }}">
          {{ $invoice->is_overdue ? 'OVERDUE' : strtoupper($invoice->status) }}
        </span>
      </p>
    </div>
  </div>

  <div class="header">
    <div class="col">
      <p class="muted" style="margin:0 0 4px">Ditagihkan kepada</p>
      <p style="margin:0;font-weight:bold">{{ $invoice->client->name }}</p>
      <p class="muted" style="margin:2px 0">{{ $invoice->client->email }}</p>
      @if ($invoice->client->company)
        <p class="muted" style="margin:2px 0">{{ $invoice->client->company }}</p>
      @endif
    </div>
    <div class="col right">
      <p class="muted" style="margin:0 0 4px">Tanggal Terbit</p>
      <p style="margin:0 0 10px">{{ $invoice->issue_date->format('d M Y') }}</p>
      <p class="muted" style="margin:0 0 4px">Jatuh Tempo</p>
      <p style="margin:0">{{ $invoice->due_date->format('d M Y') }}</p>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th>Deskripsi</th>
        <th class="amount">Jumlah</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($invoice->items as $item)
        <tr>
          <td>{{ $item->description }}</td>
          <td class="amount">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
        </tr>
      @empty
        <tr>
          <td>Invoice {{ $invoice->invoice_number }}</td>
          <td class="amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><td class="muted">Subtotal</td><td class="amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td></tr>
      <tr><td class="muted">Pajak</td><td class="amount">Rp {{ number_format($invoice->tax, 0, ',', '.') }}</td></tr>
      @if ($invoice->discount > 0)
        <tr><td class="muted">Diskon</td><td class="amount">- Rp {{ number_format($invoice->discount, 0, ',', '.') }}</td></tr>
      @endif
      <tr class="grand"><td>Total</td><td class="amount">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td></tr>
    </table>
  </div>

  @if ($invoice->status === 'paid')
    <p style="margin-top:30px">
      <span class="badge badge-paid">LUNAS</span>
      pada {{ $invoice->paid_at?->format('d M Y') }}
      @if ($invoice->payment_method) via {{ $invoice->payment_method }} @endif
    </p>
  @endif

  @php
    $pdfBanner = \App\Models\PromoBanner::live()->forPage('pdf_invoice')->orderBy('sort_order')->first();
  @endphp
  @if ($pdfBanner)
    @php
      // DomPDF butuh path file FISIK, sama seperti logo di atas.
      $pdfBannerPath = \Illuminate\Support\Facades\Storage::disk('local')->path('banners/' . $pdfBanner->image);
    @endphp
    @if (file_exists($pdfBannerPath))
      <div style="margin-top:30px">
        <img src="{{ $pdfBannerPath }}" style="width:100%;max-width:100%;border-radius:6px;">
      </div>
    @endif
  @endif

  <div class="footer">
    <p>{{ \App\Models\Setting::get('footer_text') ?: config('app.name') . ' — ' . now()->year }}</p>
    <p>Dokumen ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan basah.</p>
  </div>

</body>
</html>
