{{-- Previous / next links for a paginated list --}}
@if ($p->hasPages())
    <nav class="pager" aria-label="Pages">
        @if ($p->onFirstPage())<span class="btn sm ghost" aria-disabled="true">‹ Previous</span>@else<a class="btn sm ghost" href="{{ $p->previousPageUrl() }}" rel="prev">‹ Previous</a>@endif
        <span class="muted">Page {{ $p->currentPage() }} of {{ $p->lastPage() }}</span>
        @if ($p->hasMorePages())<a class="btn sm ghost" href="{{ $p->nextPageUrl() }}" rel="next">Next ›</a>@else<span class="btn sm ghost" aria-disabled="true">Next ›</span>@endif
    </nav>
@endif
