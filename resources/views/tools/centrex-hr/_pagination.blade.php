@if($paginator->hasPages())
  <nav class="ops-pagination" aria-label="Pagination">
    @if($paginator->onFirstPage())
      <span class="disabled" aria-disabled="true">← Précédent</span>
    @else
      <a rel="prev" href="{{ $paginator->previousPageUrl() }}">← Précédent</a>
    @endif
    <span class="ops-page-count">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())
      <a rel="next" href="{{ $paginator->nextPageUrl() }}">Suivant →</a>
    @else
      <span class="disabled" aria-disabled="true">Suivant →</span>
    @endif
  </nav>
@endif
