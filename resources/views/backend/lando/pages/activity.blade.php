@extends('backend.layouts.app')

@section('crumb', 'Activity: '.$page->path)

@section('content')
    @include('backend.partials.page-head', ['title' => 'Activity: '.($page->title ?: $page->path),
        'sub' => '<span class="mono">'.e($page->url()).'</span> · '.e($page->site?->name).' · '.e($page->status),
        'actions' => '<a class="btn ghost" href="'.route('lando.pages.index', ['site' => $page->site_id]).'">List Pages</a><a class="btn cyan" href="'.route('lando.pages.edit', $page).'">Edit Page</a>'])

    @foreach ($blocks as $b)<div class="flash bad" role="status" style="margin-bottom:12px">Blocked area <span class="mono">/{{ $b->value }}</span> covers this page{{ $b->isInForce() ? '' : ' (not in force)' }}: {{ $b->reason ?? 'no reason given' }}. <a href="{{ route('lando.blocked.areas.edit', $b) }}">Edit</a></div>@endforeach

    <div class="grid-4 stat-row">
        <div class="stat"><span>On It Now</span><strong>{{ $online->count() }}</strong><small>{{ $online->whereNotNull('customer_id')->count() }} signed in to My Account</small></div>
        <div class="stat"><span>Views Today</span><strong>{{ number_format($stats['today']) }}</strong><small>{{ number_format($stats['week']) }} in the last 7 days</small></div>
        <div class="stat"><span>Last 30 Days</span><strong>{{ number_format($stats['month']) }}</strong><small>{{ number_format($stats['people']) }} {{ Str::plural('visitor', $stats['people']) }}@if ($stats['blocked']) · {{ $stats['blocked'] }} turned away @endif</small></div>
        <div class="stat"><span>Being Edited By</span><strong style="font-size:1.05rem">{{ collect($editing)->pluck('name')->implode(', ') ?: 'Nobody' }}</strong><small>edit screen opened in the last 10 minutes</small></div>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>Views by Day</h2><span class="muted">last 30 days</span></div>
        <div class="panel-body"><x-bar-chart :bars="collect($days)->map(fn ($d) => ['label' => $d[0]->format('n/j'), 'value' => $d[1], 'tip' => $d[0]->format('D n/j')])" unit="views" /></div>
    </div>

    <div class="grid-2" style="margin-bottom:20px">
        <div class="panel"><div class="panel-head"><h2>On This Page Now</h2></div>
            <div class="table-wrap"><table><tbody>@forelse ($online as $v)
                <tr><td class="mono">{{ $v->ip }}</td><td>{{ $v->customer?->name ?? 'Visitor' }}</td><td class="nowrap muted">opened {{ $v->created_at->diffForHumans() }}</td></tr>
            @empty <tr><td class="empty">Nobody right now.</td></tr> @endforelse</tbody></table></div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Where Visitors Came From</h2><span class="muted">30 days</span></div>
            <div class="table-wrap"><table><tbody>@forelse ($referrers as $host => $n)
                <tr><td>{{ $host }}</td><td class="num">{{ $n }}</td></tr>
            @empty <tr><td class="empty">Direct visits and links inside the site only.</td></tr> @endforelse</tbody></table></div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>History</h2><span class="muted">changes to the page and its components</span></div>
        <div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>Date</th><th>User</th><th>What</th><th>Changes</th></tr></thead><tbody>
            @forelse ($history as $h)
                @php $u = \App\Support\Records::url('history_item', $h->id); @endphp<tr><td class="nowrap"><a href="{{ $u }}"><b>#{{ $h->id }}</b></a></td><td class="nowrap">{{ $h->created_at->format('n/j/Y g:i A') }}</td><td>{{ $h->actor() }}</td><td class="wrap">{{ $h->summary }}</td>
                    <td class="wrap">@if ($h->changes)<details><summary>{{ count($h->changes) }} {{ Str::plural('field', count($h->changes)) }}</summary><ul style="margin:6px 0 0 16px;font-size:.82rem">@foreach ($h->changes as $field => [$old, $new])<li><b>{{ str_replace('_', ' ', $field) }}</b>: {{ Str::limit((string) $old, 60) ?: '(empty)' }} → {{ Str::limit((string) $new, 60) ?: '(empty)' }}</li>@endforeach</ul></details>@else<span class="muted">—</span>@endif</td></tr>
            @empty <tr><td colspan="5" class="empty">No changes recorded.</td></tr> @endforelse
        </tbody></table></div>
        @include('backend.partials.pager', ['p' => $history])
    </div>

    <div class="panel"><div class="panel-head"><h2>Recent Visits</h2><a class="btn sm ghost" href="{{ route('lando.visitors', ['path' => $page->path === '/' ? null : $page->path]) }}">All</a></div>
        <div class="table-wrap"><table><tbody>@forelse ($recent as $v)
            <tr><td class="nowrap">{{ $v->created_at->format('n/j/Y g:i A') }}</td><td class="mono">{{ $v->ip }}</td><td>{{ $v->customer?->name ?? 'Visitor' }}</td><td>@if ($v->blocked)@include('backend.partials.pill', ['text' => 'Turned away', 'tone' => 'bad'])@endif</td></tr>
        @empty <tr><td class="empty">No visits yet.</td></tr> @endforelse</tbody></table></div>
    </div>
@endsection
