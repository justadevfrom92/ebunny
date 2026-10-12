@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Website Dashboard',
        'sub' => 'Site health, who is on the website, and what is blocked. Visitors count as online while a page is open and showing (it checks in every minute).',
        'actions' => '<a class="btn ghost" href="'.route('lando.visitors').'">Visitors</a><a class="btn cyan" href="'.route('lando.pages.index').'">List Pages</a>'])

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('lando.visitors', ['who' => 'online']) }}"><span>On the Site Now</span><strong>{{ number_format($online) }}</strong><small>{{ $onlineCustomers }} signed in to My Account</small></a>
        <a class="stat" href="{{ route('lando.visitors', ['start' => today()->toDateString()]) }}"><span>Visitors Today</span><strong>{{ number_format($visitorsToday) }}</strong><small>{{ number_format($viewsToday) }} page views · {{ number_format($visitors30) }} in 30 days</small></a>
        <div class="stat"><span>My Account Users</span><strong>{{ number_format($accounts) }}</strong><small>of {{ number_format($customers) }} customers · {{ $signedInToday }} signed in today</small></div>
        <a class="stat" href="{{ route('lando.health') }}"><span>Site Health</span><strong style="color:var(--{{ $problems ? 'warn' : 'ok' }})">{{ $problems ? $problems.' to look at' : 'All good' }}</strong><small>{{ count($checks) }} checks</small></a>
    </div>
    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('lando.blocked.ips') }}"><span>Blocked IPs</span><strong>{{ $blockedIps }}</strong></a>
        <a class="stat" href="{{ route('lando.blocked.areas') }}"><span>Blocked Areas</span><strong>{{ $blockedAreas }}</strong></a>
        <a class="stat" href="{{ route('lando.visitors', ['blocked' => 1, 'start' => today()->toDateString()]) }}"><span>Turned Away Today</span><strong>{{ number_format($blockedToday) }}</strong><small>by a blocked IP or area</small></a>
        <a class="stat" href="{{ route('lando.pages.index') }}"><span>Pages</span><strong>{{ \App\Models\Page::where('status', 'Published')->count() }}</strong><small>published</small></a>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>Page Views by Hour</h2><span class="muted">today and yesterday</span></div>
        <div class="panel-body">@php $top = max(1, collect($hours)->max(fn ($h) => max($h['today'], $h['yesterday']))); @endphp
            <div class="hour-chart">@foreach ($hours as $h)<div class="col" tabindex="0" data-tip="{{ date('ga', mktime($h['hour'])) }}: {{ $h['today'] }} today, {{ $h['yesterday'] }} yesterday"><div class="bars"><i style="height:{{ round($h['yesterday'] / $top * 100) }}%"></i><b style="height:{{ round($h['today'] / $top * 100) }}%"><em>{{ $h['today'] }}</em></b></div><small>{{ $h['hour'] % 2 === 0 ? date('ga', mktime($h['hour'])) : '' }}</small></div>@endforeach</div>
            @include('components.chart-tip')
            <div class="hour-legend"><span><b></b> Today</span><span><i></i> Yesterday</span></div>
        </div>
    </div>

    <div class="grid-2" style="margin-bottom:20px">
        <div class="panel"><div class="panel-head"><h2>Site Health</h2><a class="btn sm ghost" href="{{ route('lando.health') }}">Details</a></div>
            @include('backend.lando.site._checks', ['checks' => collect($checks)->sortBy(fn ($c) => ['bad' => 0, 'warn' => 1, 'ok' => 2][$c['state']])->take(8)->values()->all(), 'compact' => true])
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:20px;align-content:start;min-width:0">
            <div class="panel"><div class="panel-head"><h2>On the Site Now</h2><a class="btn sm ghost" href="{{ route('lando.visitors', ['who' => 'online']) }}">Who</a></div>
                <div class="table-wrap"><table><tbody>@forelse ($onPages as $r)
                    <tr><td class="path-cell">@if ($r->page_id)<a href="{{ route('lando.pages.activity', $r->page_id) }}">{{ $r->path }}</a>@else{{ $r->path }}@endif</td><td class="num">{{ $r->n }} {{ Str::plural('visitor', $r->n) }}</td></tr>
                @empty <tr><td class="empty">Nobody right now.</td></tr> @endforelse</tbody></table></div>
            </div>
            <div class="panel"><div class="panel-head"><h2>Top Pages Today</h2></div>
                <div class="table-wrap"><table><thead><tr><th>Page</th><th class="num">Views</th><th class="num">People</th></tr></thead><tbody>@forelse ($topPages as $r)
                    <tr><td class="path-cell">@if ($r->page_id)<a href="{{ route('lando.pages.activity', $r->page_id) }}">{{ $r->path }}</a>@else{{ $r->path }}@endif</td><td class="num">{{ $r->n }}</td><td class="num">{{ $r->people }}</td></tr>
                @empty <tr><td colspan="3" class="empty">No views yet today.</td></tr> @endforelse</tbody></table></div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Recently Turned Away</h2><a class="btn sm ghost" href="{{ route('lando.visitors', ['blocked' => 1]) }}">All</a></div>
            <div class="table-wrap"><table><tbody>@forelse ($recentBlocks as $v)
                <tr><td class="nowrap">{{ $v->created_at->format('n/j g:i A') }}</td><td class="mono">{{ $v->ip }}</td><td>{{ $v->path }}</td></tr>
            @empty <tr><td class="empty">Nobody.</td></tr> @endforelse</tbody></table></div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Latest Website Changes</h2></div>
            <div class="table-wrap"><table><tbody>@forelse ($changes as $h)
                <tr><td class="nowrap">{{ $h->created_at->format('n/j g:i A') }}</td><td>{{ $h->actor() }}</td><td class="wrap">{{ $h->summary }}</td></tr>
            @empty <tr><td class="empty">No changes recorded.</td></tr> @endforelse</tbody></table></div>
        </div>
    </div>
@endsection
