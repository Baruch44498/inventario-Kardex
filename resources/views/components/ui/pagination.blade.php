@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Paginación">
        <div class="pagination__summary">
            Mostrando
            <strong>{{ $paginator->firstItem() }}</strong>
            a
            <strong>{{ $paginator->lastItem() }}</strong>
            de
            <strong>{{ $paginator->total() }}</strong>
            registros
        </div>

        <div class="pagination__links">
            @if ($paginator->onFirstPage())
                <span class="pagination__button pagination__button--disabled">
                    Anterior
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="pagination__button"
                    rel="prev"
                >
                    Anterior
                </a>
            @endif

            @foreach ($paginator->getUrlRange(
                max(1, $paginator->currentPage() - 2),
                min($paginator->lastPage(), $paginator->currentPage() + 2)
            ) as $page => $url)
                @if ($page === $paginator->currentPage())
                    <span
                        class="pagination__button pagination__button--active"
                        aria-current="page"
                    >
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $url }}" class="pagination__button">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="pagination__button"
                    rel="next"
                >
                    Siguiente
                </a>
            @else
                <span class="pagination__button pagination__button--disabled">
                    Siguiente
                </span>
            @endif
        </div>
    </nav>
@endif
