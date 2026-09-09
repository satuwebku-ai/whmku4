{{-- Pagination bergaya Bootstrap standar -- dipakai lewat
     $items->links('pagination.bootstrap'). Baik framework.css (admin)
     maupun Bootstrap 5.3.8 asli (publik) sama-sama punya styling
     lengkap untuk .pagination/.page-link/.page-item, jadi tidak perlu
     dicampur .btn seperti versi sebelumnya. --}}
@if ($paginator->hasPages())
  <nav aria-label="Navigasi Halaman">
    <ul class="pagination mb-0">

      @if ($paginator->onFirstPage())
        <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
      @else
        <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a></li>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
            @else
              <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a></li>
      @else
        <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
      @endif
    </ul>
  </nav>
@endif
