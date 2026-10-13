@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Emails',
        'sub' => 'Every email customers get: account emails from Corral and campaign emails. Rates are of delivered emails.',
        'actions' => '<a class="btn ghost" href="'.route('rodeo.emails.sent').'">Sent Emails</a><a class="btn cyan" href="'.route('rodeo.templates.create').'">New Template</a>'])

    <div class="actions" style="margin-bottom:14px">@foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $d => $l)<a class="btn sm {{ $days === $d ? '' : 'ghost' }}" href="{{ route('rodeo.emails', ['days' => $d]) }}">{{ $l }}</a>@endforeach</div>

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('rodeo.emails.sent', ['start' => $since->toDateString()]) }}"><span>Emails</span><strong>{{ number_format($stats['total']) }}</strong><small>{{ number_format($stats['delivered']) }} delivered @if ($stats['unsent']) · {{ number_format($stats['unsent']) }} not sent @endif</small></a>
        <div class="stat"><span>Open Rate</span><strong>{{ $stats['open'] }}%</strong><small>Click rate {{ $stats['click'] }}%</small></div>
        <a class="stat" href="{{ route('rodeo.emails.sent', ['stage' => 'dropped', 'start' => $since->toDateString()]) }}"><span>Dropped</span><strong>{{ $stats['drop'] }}%</strong><small>bounced or blocked by the inbox</small></a>
        <a class="stat" href="{{ route('rodeo.emails.suppressions') }}"><span>Suppressed Addresses</span><strong>{{ number_format($suppressed) }}</strong><small>never emailed</small></a>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>Emails Sent</h2><span class="muted">{{ $days <= 30 ? 'by day' : ($days <= 90 ? 'by week' : 'by month') }}</span></div>
        <div class="panel-body"><x-bar-chart :bars="collect($chart)->map(fn ($c) => ['label' => $c[0], 'value' => $c[2], 'tip' => ucfirst($c[1])])" unit="emails" /></div>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>By Template</h2><a class="btn sm ghost" href="{{ route('rodeo.templates') }}">All Templates</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Template</th><th>Category</th><th class="num">Emails</th><th class="num">Delivered</th><th class="num">Opened</th><th class="num">Clicked</th><th class="num">Dropped</th><th>Last Sent</th></tr></thead>
            <tbody>@forelse ($rows as $r)
                @php $t = $templates[$r->template] ?? null; $pct = fn ($n) => $r->delivered ? round(100 * $n / $r->delivered).'%' : '—'; @endphp
                <tr class="click" data-href="{{ route('rodeo.emails.sent', ['template' => $r->template, 'start' => $since->toDateString()]) }}">
                    <td><a href="{{ route('rodeo.emails.sent', ['template' => $r->template, 'start' => $since->toDateString()]) }}"><b>{{ $r->template }}</b></a>@unless ($t)<div class="muted" style="font-size:.78rem">not a Rodeo template</div>@endunless</td>
                    <td>@if ($t?->category)<span class="sv-cat"><span class="sv-dot" style="--dot:{{ $t->category->color }}"></span>{{ $t->category->name }}</span>@else<span class="muted">—</span>@endif</td>
                    <td class="num">{{ number_format($r->total) }}</td><td class="num">{{ number_format($r->delivered) }}</td>
                    <td class="num">{{ $pct($r->opened) }}</td><td class="num">{{ $pct($r->clicked) }}</td>
                    <td class="num">{{ $r->dropped ? number_format($r->dropped) : '—' }}</td>
                    <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($r->last_at)->format('n/j/Y') }}</td></tr>
            @empty <tr><td colspan="8" class="empty">No emails in this time.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>By Category</h2><a class="btn sm ghost" href="{{ route('rodeo.emails.categories') }}">Categories</a></div>
            <div class="panel-body">@php $max = max(1, $byCategory->max(1)); @endphp
                <div class="sv-bars">@forelse ($byCategory as [$c, $n])<div class="sv-bar" tabindex="0" data-tip="{{ $c->name }}: {{ number_format($n) }} emails" style="--dot:{{ $c->color }}"><span>{{ $c->name }} <span class="muted">· {{ $c->templates_count }} {{ Str::plural('template', $c->templates_count) }}</span></span><span class="track"><span class="fill" style="width:{{ round($n / $max * 100) }}%"></span></span><span class="n">{{ number_format($n) }}</span></div>
                @empty <p class="muted" style="margin:0">No categories yet.</p> @endforelse</div>
            </div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Latest Emails</h2><a class="btn sm ghost" href="{{ route('rodeo.emails.sent') }}">All</a></div>
            <div class="table-wrap"><table><tbody>@forelse ($recent as $e)
                <tr class="click" data-href="{{ route('rodeo.emails.sent.show', $e) }}"><td class="nowrap">{{ $e->created_at->format('n/j g:i A') }}</td><td><a href="{{ route('rodeo.emails.sent.show', $e) }}">{{ $e->template }}</a><div class="muted">{{ $e->customer?->name }}</div></td><td>@include('backend.rodeo.emails._stage', ['e' => $e])</td></tr>
            @empty <tr><td class="empty">No emails yet.</td></tr> @endforelse</tbody></table></div>
        </div>
    </div>
@endsection
