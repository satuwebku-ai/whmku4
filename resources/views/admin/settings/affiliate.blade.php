@extends('layouts.admin')

@section('title', 'Pengaturan Affiliate')

@section('content')

  @include('admin.settings._nav')

  @php use App\Models\Setting; @endphp

  <h1 class="h4 fw-bold text-dark mb-1">Program Affiliate</h1>

  <div class="mb-4">
    <p class="small text-muted mb-0">
      Ini mengatur komisi <b>default</b> untuk affiliate baru dan affiliate yang belum diberi tarif khusus.
      Untuk memberi tarif berbeda ke satu affiliate tertentu, buka detail affiliate itu di
      <a href="{{ route('admin.affiliate.index') }}">Penjualan → Affiliate</a>.
    </p>
  </div>

  <form method="POST" action="{{ route('admin.settings.affiliate.update') }}" class="card border rounded-4 p-4" style="max-width:40rem">
    @csrf

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Jenis Komisi</label>
        <select name="affiliate_commission_type" class="form-select form-select-sm">
          <option value="percentage" @selected(Setting::get('affiliate_commission_type', 'percentage') === 'percentage')>Persentase (%) dari nilai transaksi</option>
          <option value="fixed" @selected(Setting::get('affiliate_commission_type', 'percentage') === 'fixed')>Nominal tetap (Rp) per transaksi</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark" id="affValueLabel">Nilai Komisi</label>
        <input type="number" step="0.01" min="0" name="affiliate_commission_value" value="{{ Setting::get('affiliate_commission_value', 10) }}" class="form-control form-control-sm">
      </div>
    </div>

    <div class="mb-3">
      <label class="d-flex align-items-start gap-2 small text-dark mb-0">
        <input type="checkbox" name="affiliate_commission_repeat" value="1" @checked(Setting::get('affiliate_commission_repeat', true)) class="form-check-input mt-1">
        <span>
          <b>Berulang tiap perpanjangan</b> — affiliate tetap dapat komisi setiap kali klien referralnya membayar invoice perpanjangan (hosting/VPS/domain), bukan cuma sekali di pembelian pertama.
        </span>
      </label>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Minimal Payout</label>
      <div class="input-group" style="max-width:16rem">
        <span class="input-group-text">Rp</span>
        <input type="number" step="1000" min="0" name="affiliate_min_payout" value="{{ Setting::get('affiliate_min_payout', 50000) }}" class="form-control form-control-sm">
      </div>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Affiliate baru bisa minta cair kalau saldo wallet-nya sudah mencapai minimal ini.</p>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Masa Cookie Referral (hari)</label>
        <input type="number" min="1" max="730" name="affiliate_cookie_days" value="{{ Setting::get('affiliate_cookie_days', 30) }}" class="form-control form-control-sm">
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Model Atribusi</label>
        <select name="affiliate_attribution_model" class="form-select form-select-sm">
          <option value="first_click" @selected(Setting::get('affiliate_attribution_model', 'first_click') === 'first_click')>First click</option>
          <option value="last_click" @selected(Setting::get('affiliate_attribution_model', 'first_click') === 'last_click')>Last click</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Aturan Pembayaran</label>
        <select name="affiliate_commission_mode" class="form-select form-select-sm">
          <option value="first_order" @selected(Setting::get('affiliate_commission_mode', 'every_payment') === 'first_order')>Order pertama saja</option>
          <option value="first_payment" @selected(Setting::get('affiliate_commission_mode', 'every_payment') === 'first_payment')>Pembayaran pertama</option>
          <option value="every_payment" @selected(Setting::get('affiliate_commission_mode', 'every_payment') === 'every_payment')>Setiap pembayaran</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label small fw-medium text-dark">Pajak / Withholding (%)</label>
        <div class="input-group">
          <input type="number" step="0.01" min="0" max="100" name="affiliate_tax_rate" value="{{ Setting::get('affiliate_tax_rate', 0) }}" class="form-control form-control-sm">
          <span class="input-group-text">
            <input type="checkbox" name="affiliate_tax_enabled" value="1" @checked(Setting::get('affiliate_tax_enabled', false)) class="form-check-input me-1"> aktif
          </span>
        </div>
      </div>
    </div>

    <div class="rounded-3 p-3 mb-3" style="background:#eef2ff;color:#4338ca;font-size:13px">
      <i class="fa-solid fa-circle-info"></i>
      Ringkasan program saat ini: affiliate dapat
      <b>{{ Setting::get('affiliate_commission_type', 'percentage') === 'percentage' ? Setting::get('affiliate_commission_value', 10) . '%' : 'Rp ' . number_format((float) Setting::get('affiliate_commission_value', 10), 0, ',', '.') }}</b>
      dari {{ Setting::get('affiliate_commission_type', 'percentage') === 'percentage' ? 'nilai' : 'setiap' }} transaksi klien yang mereka referensikan,
      {{ Setting::get('affiliate_commission_repeat', true) ? 'berulang setiap perpanjangan' : 'satu kali saja di pembelian pertama' }}.
    </div>

    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
  </form>

  <script>
    document.querySelector('[name="affiliate_commission_type"]').addEventListener('change', function (e) {
      document.getElementById('affValueLabel').textContent = e.target.value === 'percentage' ? 'Nilai Komisi (%)' : 'Nilai Komisi (Rp)';
    });
    document.querySelector('[name="affiliate_commission_type"]').dispatchEvent(new Event('change'));
  </script>

@endsection
