{{-- Page links for a paginated list: ‹ 1 2 3 … 9 › --}}
@if ($p->hasPages())
    @php
        $cur = $p->currentPage();
        $last = method_exists($p, 'lastPage') ? $p->lastPage() : null;
        $nums = $last ? collect([1, $last, ...range(max(1, $cur - 2), min($last, $cur + 2))])->unique()->sort()->values() : collect();
    @endphp
    <nav class="pager" aria-label="Pages">
        @if ($p->onFirstPage())<span class="btn sm ghost" aria-disabled="true" aria-label="Previous page">‹</span>@else<a class="btn sm ghost" href="{{ $p->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>@endif
        @foreach ($nums as $i => $n)
            @if ($i > 0 && $n - $nums[$i - 1] > 1)<span class="muted">…</span>@endif
            @if ($n === $cur)<span class="btn sm on" aria-current="page">{{ $n }}</span>@else<a class="btn sm ghost" href="{{ $p->url($n) }}">{{ $n }}</a>@endif
        @endforeach
        @if ($p->hasMorePages())<a class="btn sm ghost" href="{{ $p->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>@else<span class="btn sm ghost" aria-disabled="true" aria-label="Next page">›</span>@endif
        @if ($last)<span class="muted pager-of">{{ number_format($p->firstItem()) }}–{{ number_format($p->lastItem()) }} of {{ number_format($p->total()) }}</span>@endif
    </nav>
@endif
