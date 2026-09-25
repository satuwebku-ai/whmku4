@extends('layouts.admin')

@section('title', 'Support Ticket')

@section('content')

  <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:#ecfdf5;color:#059669"><i class="fa-solid fa-ticket"></i></span>
        <h1 class="h4 fw-bold text-dark mb-0">Support Ticket</h1>
      </div>
      <p class="small text-muted mb-0">Kelola pertanyaan klien dengan SLA, prioritas, penanggung jawab, dan riwayat email yang jelas.</p>
    </div>
    <a href="{{ route('admin.ticket.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Tiket
    </a>
  </div>

  <div class="row g-2 mb-3">
    @foreach ([
      ['label' => 'Total tiket', 'value' => $stats['all'], 'icon' => 'fa-layer-group', 'tone' => 'primary'],
      ['label' => 'Perlu ditangani', 'value' => $stats['open'] + $stats['customer_reply'], 'icon' => 'fa-inbox', 'tone' => 'warning'],
      ['label' => 'Prioritas tinggi', 'value' => $stats['urgent'], 'icon' => 'fa-bolt', 'tone' => 'danger'],
      ['label' => 'Selesai', 'value' => $stats['closed'], 'icon' => 'fa-circle-check', 'tone' => 'success'],
    ] as $stat)
      <div class="col-6 col-xl-3">
        <div class="card border rounded-4 px-3 py-3 h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div><p class="text-muted mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:.06em">{{ $stat['label'] }}</p><p class="h5 fw-bold text-dark mb-0">{{ $stat['value'] }}</p></div>
            <span class="rounded-3 d-flex align-items-center justify-content-center text-{{ $stat['tone'] }}" style="width:34px;height:34px;background:rgba(var(--bs-{{ $stat['tone'] }}-rgb),.1)"><i class="fa-solid {{ $stat['icon'] }}" style="font-size:13px"></i></span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Tab status --}}
  <div class="d-flex align-items-center gap-1 mb-3 border-bottom flex-wrap">
    @php
      $tabs = [
        ['label' => 'Semua', 'route' => 'admin.tickets'],
        ['label' => 'Baru', 'route' => 'admin.tickets.open'],
        ['label' => 'Balasan Klien', 'route' => 'admin.tickets.customer-reply'],
        ['label' => 'Dijawab', 'route' => 'admin.tickets.answered'],
        ['label' => 'Ditutup', 'route' => 'admin.tickets.closed'],
      ];
    @endphp
    @foreach ($tabs as $tab)
      <a href="{{ route($tab['route']) }}"
         class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route'])) ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor tiket / subjek..." class="form-control form-control-sm" style="max-width:16rem;flex:1 1 180px">
      <select name="priority" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:10rem">
        <option value="">Semua Prioritas</option>
        <option value="urgent" @selected(request('priority') === 'urgent')>Urgent</option>
        <option value="high" @selected(request('priority') === 'high')>High</option>
        <option value="medium" @selected(request('priority') === 'medium')>Medium</option>
        <option value="low" @selected(request('priority') === 'low')>Low</option>
      </select>
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Filter</button>
      @if (request('search') || request('priority'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Tiket</th>
            <th class="py-3">Klien</th>
            <th class="py-3">Departemen</th>
            <th class="py-3">Prioritas</th>
            <th class="py-3">Ditugaskan</th>
            <th class="py-3">Status</th>
            <th class="py-3">Update</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'overdue' => 'badge-soft-danger', 'suspended' => 'badge-soft-danger', 'inactive' => 'badge-soft-secondary'];
          @endphp
          @forelse ($tickets as $ticket)
             <tr class="{{ $ticket->needsAttention() ? 'table-warning-subtle' : '' }}" style="{{ $ticket->needsAttention() ? 'background:#fffbeb' : '' }}">
              <td class="px-4 py-3">
                <a href="{{ route('admin.tickets.details', $ticket) }}" class="text-decoration-none fw-medium text-dark">
                  {{ $ticket->subject }}
                </a>
                 <p class="text-muted mb-0" style="font-size:11px"><span class="font-monospace">{{ $ticket->ticket_number }}</span> · {{ $ticket->replies_count }} balasan</p>
              </td>
              <td class="text-muted py-3">{{ $ticket->client->name ?? '—' }}</td>
              <td class="text-muted text-capitalize py-3">{{ $ticket->department }}</td>
              <td class="py-3"><span class="badge {{ $badgeMap[$ticket->priority_badge] ?? 'badge-soft-secondary' }}">{{ ucfirst($ticket->priority) }}</span></td>
              <td class="text-muted py-3">{{ $ticket->assignee->name ?? '—' }}</td>
              <td class="py-3"><span class="badge {{ $badgeMap[$ticket->status_badge] ?? 'badge-soft-secondary' }}">{{ $ticket->status_label }}</span></td>
              <td class="text-muted py-3" style="font-size:12px">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.tickets.details', $ticket) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Buka">
                    <i class="fa-regular fa-comments" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.ticket.delete', $ticket) }}" data-confirm="Hapus tiket ini beserta semua balasannya?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada tiket di kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($tickets->hasPages())
      <div class="px-4 py-3 border-top">{{ $tickets->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
