@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Customer Search', 'sub' => 'Find an account by name, phone, email, account number, ESIID or address.'])

    <div class="grid-4">
        <div class="stat"><span>Accounts</span><strong>{{ number_format($stats['accounts']) }}</strong></div>
        <div class="stat"><span>On Flow</span><strong>{{ number_format($stats['onFlow']) }}</strong><small>utility accepted</small></div>
        <div class="stat"><span>Pending</span><strong>{{ number_format($stats['pending']) }}</strong><small>credit, deposit or utility</small></div>
        <div class="stat alert"><span>Exceptions</span><strong>{{ number_format($stats['exceptions']) }}</strong><small>need attention</small></div>
    </div>

    {{-- Search and Bookmarks side by side on desktop (50% each), full width on tablet and phone --}}
    <div class="search-bookmarks">
    <div class="panel"><div class="panel-head"><h2>Search</h2></div><div class="panel-body">
        <form method="get" class="form-row">
            <div class="field grow"><label for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, phone, email, account #, ESIID…"></div>
            <div class="field"><label for="type">Customer Type</label>
                <select id="type" name="type" data-autosubmit><option value="">-- All Customer Types --</option>
                    @foreach (['Residential', 'Small Business'] as $t)<option @selected(($filters['type'] ?? '') === $t)>{{ $t }}</option>@endforeach
                </select></div>
            <div class="field"><label for="status">Status</label>
                <select id="status" name="status" data-autosubmit><option value="">-- All Statuses --</option>
                    @foreach (array_keys(config('admin.customer_statuses')) as $s)<option @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach
                </select></div>
            <div class="field"><label for="exception">Exception</label>
                <select id="exception" name="exception" data-autosubmit><option value="">-- Exceptions --</option><option value="*" @selected(($filters['exception'] ?? '') === '*')>Any Exception</option>
                    @foreach (config('admin.exceptions') as $e)<option @selected(($filters['exception'] ?? '') === $e)>{{ $e }}</option>@endforeach
                </select></div>
            <div class="field"><label for="limit">Limit</label>
                <select id="limit" name="limit" data-autosubmit>
                    @foreach ([10, 25, 50, 100, 0] as $l)<option value="{{ $l }}" @selected($filters['limit'] === $l)>{{ $l ? $l : 'No Limit' }}</option>@endforeach
                </select></div>
            <button class="btn">Search</button>
        </form>
    </div></div>

    @unless ($searched)
        <div class="panel"><div class="panel-head"><h2>Bookmarks</h2></div>
            @include('backend.corral.customers._rows', ['customers' => $bookmarks, 'empty' => 'No bookmarks yet. Open an account and choose Bookmark.'])
        </div>
    @endunless
    </div>

    <div class="panel"><div class="panel-head"><h2>{{ $searched ? 'Search Results' : 'Recent Accounts' }}</h2><span class="muted">Showing {{ $results->count() ? number_format($results->firstItem()).'–'.number_format($results->lastItem()) : 0 }} of {{ number_format($total) }}</span></div>
        @include('backend.corral.customers._rows', ['customers' => $results, 'empty' => 'No accounts match. Try fewer filters.'])
        @include('backend.partials.pager', ['p' => $results])
    </div>
@endsection
