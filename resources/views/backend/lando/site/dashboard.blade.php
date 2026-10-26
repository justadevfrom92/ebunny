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
            <div class="hour-legend"><span><b></b> Today</span><span><i></i> Yesterday</span></div>
        </div>
    </div>

    {{-- Who is on the website right now: one row per person, most recently active first --}}
    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>On the Site Now</h2><span class="actions"><span class="muted">{{ number_format($online) }} online · top 10</span><a class="btn sm ghost" href="{{ route('lando.visitors', ['who' => 'online']) }}">All Visitors Online</a></span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Who</th><th>On Page</th><th class="num">Pages Today</th><th>Arrived</th><th>Last Seen</th><th>Came From</th><th>Device</th><th>IP</th></tr></thead>
            <tbody>@forelse ($onSite as $o)
                @php $v = $o->visit; $url = route('lando.visitors', ['visitor' => $v->visitor]); @endphp
                <tr class="click" data-href="{{ $url }}">
                    <td>@if ($v->customer)<a href="{{ $url }}"><b>{{ $v->customer->name }}</b></a><div class="help">@include('backend.partials.account-link', ['c' => $v->customer]) · signed in</div>@else<a href="{{ $url }}"><b>Visitor</b></a><div class="help mono">{{ \Illuminate\Support\Str::limit($v->visitor, 10, '…') }}</div>@endif</td>
                    <td class="path-cell">/{{ ltrim($v->path === '/' ? '' : $v->path, '/') }}@if ($o->trail->count() > 1)<div class="help">before: {{ $o->trail->reject(fn ($p) => $p === $v->path)->map(fn ($p) => '/'.ltrim($p === '/' ? '' : $p, '/'))->implode(', ') }}</div>@endif</td>
                    <td class="num">{{ $o->pages }}</td>
                    <td class="nowrap">{{ $o->arrived->format('g:i A') }}<div class="help">{{ $o->arrived->diffForHumans(null, true) }} on site</div></td>
                    <td class="nowrap">{{ $v->seen_at->diffForHumans() }}</td>
                    <td class="wrap">{{ $o->from ? parse_url($o->from, PHP_URL_HOST) ?: $o->from : 'Direct' }}</td>
                    <td class="nowrap">{{ $o->device }}<div class="help">{{ $o->browser }}</div></td>
                    <td class="mono">{{ $v->ip }}</td></tr>
            @empty <tr><td colspan="8" class="empty">Nobody is on the website right now.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="grid-2" style="margin-bottom:20px">
        <div class="panel"><div class="panel-head"><h2>Site Health</h2><a class="btn sm ghost" href="{{ route('lando.health') }}">Details</a></div>
            @include('backend.lando.site._checks', ['checks' => collect($checks)->sortBy(fn ($c) => ['bad' => 0, 'warn' => 1, 'ok' => 2][$c['state']])->take(8)->values()->all(), 'compact' => true])
        </div>
        <div class="panel"><div class="panel-head"><h2>Top Pages Today</h2></div>
            <div class="table-wrap"><table><thead><tr><th>Page</th><th class="num">Views</th><th class="num">People</th></tr></thead><tbody>@forelse ($topPages as $r)
                <tr><td class="path-cell">@if ($r->page_id)<a href="{{ route('lando.pages.activity', $r->page_id) }}">{{ $r->path }}</a>@else{{ $r->path }}@endif</td><td class="num">{{ $r->n }}</td><td class="num">{{ $r->people }}</td></tr>
            @empty <tr><td colspan="3" class="empty">No views yet today.</td></tr> @endforelse</tbody></table></div>
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
