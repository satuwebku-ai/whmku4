@extends('layouts.admin')

@section('title', 'Template Notifikasi')

@section('content')

  <div class="d-flex align-items-start gap-3 mb-4">
    <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;background:#eef2ff;color:#4f46e5"><i class="fa-solid fa-envelope-open-text"></i></span>
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Template Notifikasi</h1>
    <p class="small text-muted mb-0">
      Atur kata-kata di setiap email &amp; pesan WhatsApp otomatis. Klik salah satu untuk mengedit —
      selama belum pernah diedit, sistem memakai kata-kata bawaan.
    </p>
    </div>
  </div>

  <div class="row g-2 mb-3" style="max-width:56rem">
    <div class="col-sm-4"><div class="card border rounded-4 p-3"><p class="text-muted mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:.06em">Total template</p><p class="h5 fw-bold mb-0">{{ count($templates) }}</p></div></div>
    <div class="col-sm-4"><div class="card border rounded-4 p-3"><p class="text-muted mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:.06em">Sudah dikustom</p><p class="h5 fw-bold text-success mb-0">{{ collect($templates)->where('is_customized', true)->count() }}</p></div></div>
    <div class="col-sm-4"><div class="card border rounded-4 p-3"><p class="text-muted mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:.06em">Kanal aktif</p><p class="h5 fw-bold text-accent mb-0">Email · WA · SMS</p></div></div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div>
      @foreach ($templates as $tpl)
        <a href="{{ route('admin.notification-templates.edit', $tpl['key']) }}"
           class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom text-decoration-none notif-tpl-row">
            <div class="d-flex align-items-center gap-3 min-w-0">
             <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;background:linear-gradient(135deg,#eef2ff,#f5f3ff);color:#4f46e5">
              <i class="fa-regular fa-envelope" style="font-size:14px"></i>
            </span>
            <div class="min-w-0">
              <p class="fw-medium text-dark mb-0" style="font-size:14px">{{ $tpl['label'] }}</p>
              @if (! empty($tpl['note']))
                <p class="text-muted mb-0 mt-1" style="font-size:11px">{{ $tpl['note'] }}</p>
              @endif
            </div>
          </div>
          <div class="d-flex align-items-center gap-3 flex-shrink-0 ms-3">
            @if ($tpl['is_customized'])
              <span class="badge badge-soft-success">Sudah Diedit</span>
            @else
              <span class="badge badge-soft-secondary">Bawaan</span>
            @endif
            <i class="fa-solid fa-chevron-right text-muted" style="font-size:11px"></i>
          </div>
        </a>
      @endforeach
    </div>
  </div>

  <style>
    .notif-tpl-row{ transition:background-color .12s ease; }
    .notif-tpl-row:hover{ background-color:#f8fafc; }
    .notif-tpl-row:last-child{ border-bottom:none!important; }
  </style>

@endsection
