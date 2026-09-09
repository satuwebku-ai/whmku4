@extends('layouts.admin')

@section('title', 'Template Notifikasi')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Template Notifikasi</h1>
    <p class="small text-muted mb-0">
      Atur kata-kata di setiap email &amp; pesan WhatsApp otomatis. Klik salah satu untuk mengedit —
      selama belum pernah diedit, sistem memakai kata-kata bawaan.
    </p>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div>
      @foreach ($templates as $tpl)
        <a href="{{ route('admin.notification-templates.edit', $tpl['key']) }}"
           class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom text-decoration-none notif-tpl-row">
          <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;background:rgba(79,70,229,.1);color:#4f46e5">
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
