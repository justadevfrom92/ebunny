@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Visitors', 'sub' => number_format($visits->total()).' page '.Str::plural('view', $visits->total()).' · '.$online.' '.Str::plural('visitor', $online).' on the site now. Each browser gets its own visitor id.'])

    <div class="search-layout">
        <aside class="search-side">
            <form method="get" class="panel"><div class="panel-head"><h2>Search Visitors</h2></div><div class="panel-body">
                <div class="field"><label for="who">Show</label><select id="who" name="who"><option value="">Everyone</option><option value="online" @selected(($f['who'] ?? '') === 'online')>On the site now</option><option value="customers" @selected(($f['who'] ?? '') === 'customers')>Signed in to My Account</option></select></div>
                <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] ?? '' }}"></div>
                <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] ?? '' }}"></div>
                <div class="field"><label for="ip">IP Address</label><input id="ip" name="ip" value="{{ $f['ip'] ?? '' }}" placeholder="203.0.113.7 or 203.0.113.*"></div>
                <div class="field"><label for="path">Page</label><input id="path" name="path" value="{{ $f['path'] ?? '' }}" placeholder="plans"></div>
                <div class="field"><label for="blocked">Blocked</label><select id="blocked" name="blocked"><option value="">Either</option><option value="0" @selected(($f['blocked'] ?? '') === '0')>Let in</option><option value="1" @selected(($f['blocked'] ?? '') === '1')>Turned away</option></select></div>
                @if ($f['visitor'] ?? null)<input type="hidden" name="visitor" value="{{ $f['visitor'] }}"><p class="muted" style="margin:0">One visitor: <span class="mono">{{ Str::limit($f['visitor'], 10) }}</span></p>@endif
                <button class="btn">Search</button>@if (array_filter($f, fn ($v) => $v !== null && $v !== ''))<a class="btn ghost" href="{{ route('lando.visitors') }}">Clear</a>@endif
            </div></form>
        </aside>
        <div class="search-main">
            <div class="panel"><div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Page</th><th>Visitor</th><th>Status</th><th></th></tr></thead>
                <tbody>@forelse ($visits as $v)
                    @php $isBlocked = $blocked->first(fn ($b) => $b->matchesIp($v->ip)); $on = $v->seen_at >= now()->subMinutes(\App\Models\SiteVisit::ONLINE_MINUTES); @endphp
                    <tr><td class="nowrap">{{ $v->created_at->format('n/j/Y g:i A') }}@if ($v->seen_at->gt($v->created_at->copy()->addMinute()))<div class="muted" style="font-size:.78rem">stayed {{ $v->created_at->diffForHumans($v->seen_at, true) }}</div>@endif</td>
                        <td>@if ($v->page_id)<a href="{{ route('lando.pages.activity', $v->page_id) }}">{{ $v->path }}</a>@else{{ $v->path }}@endif @if ($v->referrer)<div class="muted" style="font-size:.78rem">from {{ parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer }}</div>@endif</td>
                        <td><span class="mono">{{ $v->ip }}</span>@if ($v->customer)<div>{{ $v->customer->name }} @include('backend.partials.account-link', ['c' => $v->customer])</div>@endif<div><a class="muted" style="font-size:.78rem" href="{{ route('lando.visitors', ['visitor' => $v->visitor]) }}">all pages this visitor saw</a></div></td>
                        <td>@if ($v->blocked)@include('backend.partials.pill', ['text' => 'Turned away', 'tone' => 'bad'])@elseif ($on)@include('backend.partials.pill', ['text' => 'On now', 'tone' => 'ok'])@else<span class="muted">—</span>@endif</td>
                        <td class="actions">@if ($isBlocked)<a class="btn sm ghost" href="{{ route('lando.blocked.ips.edit', $isBlocked) }}">Blocked</a>@else<a class="btn sm ghost" href="{{ route('lando.blocked.ips.create', ['value' => $v->ip]) }}">Block IP</a>@endif</td></tr>
                @empty <tr><td colspan="5" class="empty">No visits match.</td></tr> @endforelse</tbody>
            </table></div>
            @include('backend.partials.pager', ['p' => $visits])</div>
        </div>
    </div>
@endsection
