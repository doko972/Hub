@if ($paginator->hasPages())
<nav class="hub-pagination" aria-label="Pagination">

    {{-- Précédent --}}
    @if ($paginator->onFirstPage())
        <span class="hub-pagination__btn hub-pagination__btn--disabled">← Précédent</span>
    @else
        <a class="hub-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Précédent</a>
    @endif

    {{-- Pages --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="hub-pagination__btn hub-pagination__btn--dots">{{ $element }}</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="hub-pagination__btn hub-pagination__btn--active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="hub-pagination__btn" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Suivant --}}
    @if ($paginator->hasMorePages())
        <a class="hub-pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant →</a>
    @else
        <span class="hub-pagination__btn hub-pagination__btn--disabled">Suivant →</span>
    @endif

</nav>
@endif
