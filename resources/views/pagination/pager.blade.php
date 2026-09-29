{{-- Pager selalu tampil (walau cuma 1 halaman): info jumlah data + tombol
     Pertama/Sebelumnya/nomor/Berikutnya/Terakhir. Tombol yang tidak bisa
     dipakai tampil disabled, bukan hilang. Dipakai lewat
     $items->links('pagination.pager'). Cocok untuk admin (framework.css)
     maupun publik (Bootstrap 5.3.8). --}}
@php
  $current = $paginator->currentPage();
  $last = $paginator->lastPage();
  $from = $paginator->firstItem() ?? 0;
  $to = $paginator->lastItem() ?? 0;
  $window = collect(range(max(1, $current - 2), min($last, $current + 2)));
@endphp
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size:13px">
  <span class="text-muted">
    Menampilkan {{ number_format($from, 0, ',', '.') }}–{{ number_format($to, 0, ',', '.') }}
    dari {{ number_format($paginator->total(), 0, ',', '.') }} domain
    · Halaman {{ $current }} dari {{ $last }}
  </span>

  <nav aria-label="Navigasi halaman">
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item {{ $current <= 1 ? 'disabled' : '' }}">
        @if ($current > 1)
          <a class="page-link" href="{{ $paginator->url(1) }}" aria-label="Halaman pertama">&laquo;</a>
        @else
          <span class="page-link" aria-disabled="true">&laquo;</span>
        @endif
      </li>
      <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
        @if (! $paginator->onFirstPage())
          <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Sebelumnya</a>
        @else
          <span class="page-link" aria-disabled="true">&lsaquo; Sebelumnya</span>
        @endif
      </li>

      @if ($window->first() > 1)
        <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
        @if ($window->first() > 2)<li class="page-item disabled"><span class="page-link">…</span></li>@endif
      @endif

      @foreach ($window as $page)
        <li class="page-item {{ $page === $current ? 'active' : '' }}" @if ($page === $current) aria-current="page" @endif>
          @if ($page === $current)
            <span class="page-link">{{ $page }}</span>
          @else
            <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
          @endif
        </li>
      @endforeach

      @if ($window->last() < $last)
        @if ($window->last() < $last - 1)<li class="page-item disabled"><span class="page-link">…</span></li>@endif
        <li class="page-item"><a class="page-link" href="{{ $paginator->url($last) }}">{{ $last }}</a></li>
      @endif

      <li class="page-item {{ ! $paginator->hasMorePages() ? 'disabled' : '' }}">
        @if ($paginator->hasMorePages())
          <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya &rsaquo;</a>
        @else
          <span class="page-link" aria-disabled="true">Berikutnya &rsaquo;</span>
        @endif
      </li>
      <li class="page-item {{ $current >= $last ? 'disabled' : '' }}">
        @if ($current < $last)
          <a class="page-link" href="{{ $paginator->url($last) }}" aria-label="Halaman terakhir">&raquo;</a>
        @else
          <span class="page-link" aria-disabled="true">&raquo;</span>
        @endif
      </li>
    </ul>
  </nav>
</div>
