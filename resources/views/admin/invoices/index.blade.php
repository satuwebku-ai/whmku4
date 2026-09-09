@extends('layouts.admin')

@section('title', 'Invoice')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Invoice</h1>
      <p class="small text-muted mb-0">Kelola tagihan dan status pembayaran klien.</p>
    </div>
    <a href="{{ route('admin.invoice.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Invoice
    </a>
  </div>

  {{-- Tab status --}}
  <div class="d-flex align-items-center gap-1 mb-3 border-bottom flex-wrap">
    @php
      $tabs = [
        ['label' => 'Semua', 'route' => 'admin.invoices', 'status' => null],
        ['label' => 'Belum Bayar', 'route' => 'admin.invoices.unpaid', 'status' => 'unpaid'],
        ['label' => 'Lunas', 'route' => 'admin.invoices.paid', 'status' => 'paid'],
        ['label' => 'Overdue', 'route' => 'admin.invoices.overdue', 'status' => 'overdue'],
        ['label' => 'Dibatalkan', 'route' => 'admin.invoices.cancelled', 'status' => 'cancelled'],
      ];
    @endphp
    @foreach ($tabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ $activeStatus === $tab['status'] ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor invoice..." class="form-control form-control-sm" style="max-width:20rem;flex:1 1 200px">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
      @if (request('search'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">No. Invoice</th>
            <th class="py-3">Klien</th>
            <th class="py-3">Jatuh Tempo</th>
            <th class="py-3">Status</th>
            <th class="text-end py-3">Total</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $statusBadge = [
              'paid' => 'badge-soft-success',
              'unpaid' => 'badge-soft-warning',
              'overdue' => 'badge-soft-danger',
              'cancelled' => 'badge-soft-secondary',
            ];
          @endphp
          @forelse ($invoices as $invoice)
            @php $displayStatus = $invoice->is_overdue ? 'overdue' : $invoice->status; @endphp
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <a href="{{ route('admin.invoices.details', $invoice) }}" class="text-decoration-none text-dark">{{ $invoice->invoice_number }}</a>
              </td>
              <td class="text-muted py-3">{{ $invoice->client->name ?? '—' }}</td>
              <td class="text-muted py-3">{{ $invoice->due_date->format('d M Y') }}</td>
              <td class="py-3">
                <span class="badge {{ $statusBadge[$displayStatus] ?? 'badge-soft-secondary' }}">
                  {{ $displayStatus === 'overdue' ? 'Overdue' : ucfirst($displayStatus) }}
                </span>
              </td>
              <td class="text-end text-dark py-3">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.invoices.details', $invoice) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Detail">
                    <i class="fa-regular fa-eye" style="font-size:12px"></i>
                  </a>
                  <a href="{{ route('admin.invoice.edit.page', $invoice) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.invoice.delete', $invoice) }}" data-confirm="Hapus invoice ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada invoice di kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($invoices->hasPages())
      <div class="px-4 py-3 border-top">{{ $invoices->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
