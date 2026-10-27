@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Marketing', 'sub' => 'Campaigns, customer surveys and where sign-ups come from.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.campaigns.create').'">New Campaign</a>'])
    <div class="grid-4 stat-row">
        <div class="stat"><span>Opted In to Marketing</span><strong>{{ number_format($optedIn) }}</strong><small>of {{ number_format($customers) }} customers</small></div>
        <div class="stat"><span>Campaigns Sent</span><strong>{{ $campaigns }}</strong><small>{{ number_format($messages) }} messages</small></div>
        <div class="stat"><span>Email Open / Click Rate</span><strong>{{ $openRate }}% / {{ $clickRate }}%</strong></div>
        <a class="stat" href="{{ route('rodeo.surveys') }}"><span>Survey Responses</span><strong>{{ number_format($responses) }}</strong></a>
    </div>
    {{-- Surveys: every survey's responses and score, then the latest answers --}}
    <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Surveys</h2><span class="muted">{{ number_format($responses30) }} responses in the last 30 days · <a href="{{ route('rodeo.surveys') }}">All Surveys</a></span></div><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Survey</th><th>Status</th><th class="num">Last 30 Days</th><th class="num">All Responses</th><th class="num">Score (NPS)</th><th>Last Response</th></tr></thead>
        <tbody>@forelse ($surveys as $s)
            @php $u = route('rodeo.surveys.results', $s); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $s->id }}</b></a></td><td><b>{{ $s->title }}</b></td>
                <td>@include('backend.partials.pill', ['text' => $s->stateLabel(), 'tone' => ['open' => 'ok', 'scheduled' => 'info', 'ended' => '', 'closed' => ''][$s->state()] ?? ''])</td>
                <td class="num">{{ number_format($s->recent_count) }}</td><td class="num">{{ number_format($s->responses_count) }}</td>
                <td class="num">@if ($s->score !== null)<b @style(['color:var(--ok)' => $s->score >= 30, 'color:var(--bad)' => $s->score < 0])>{{ $s->score > 0 ? '+' : '' }}{{ $s->score }}</b>@else — @endif</td>
                <td class="nowrap">{{ $s->responses_max_created_at ? \Illuminate\Support\Carbon::parse($s->responses_max_created_at)->format('n/j/Y') : '—' }}</td></tr>
        @empty <tr><td colspan="7" class="empty">No surveys yet.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Latest Survey Responses</h2><a class="btn sm ghost" href="{{ route('rodeo.surveys.responses') }}">All Responses</a></div><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>When</th><th>Survey</th><th>Customer</th><th class="num">Score</th></tr></thead>
        <tbody>@forelse ($latestResponses as $r)
            @php $u = route('rodeo.surveys.responses.show', $r); $n = $r->nps(); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $r->id }}</b></a></td><td class="nowrap">{{ $r->created_at->format('n/j/Y g:i A') }}</td>
                <td>{{ $r->survey?->title }}</td><td>@if ($r->customer){{ $r->customer->name }}<div class="help">@include('backend.partials.account-link', ['c' => $r->customer])</div>@else<span class="muted">Anonymous</span>@endif</td>
                <td class="num">{{ $n ?? '—' }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No responses yet.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Sign-ups by MSID</h2><span class="muted">last 90 days</span></div><div class="table-wrap"><table>
            <thead><tr><th>MSID</th><th>Channel</th><th class="num">Orders</th></tr></thead>
            <tbody>@forelse ($byChannel as [$msid, $name, $n])<tr><td class="mono">{{ $msid }}</td><td>{{ $name }}</td><td class="num">{{ $n }}</td></tr>@empty<tr><td colspan="3" class="empty">No orders.</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Recent Campaigns</h2><a class="btn sm ghost" href="{{ route('rodeo.campaigns') }}">All</a></div><div class="table-wrap"><table>
            <tbody>@forelse ($recent as $c)<tr class="click" data-href="{{ route('rodeo.campaigns.edit', $c) }}"><td><a href="{{ route('rodeo.campaigns.edit', $c) }}">{{ $c->name }}</a></td><td>{{ $c->channel }}</td><td>@include('backend.partials.pill', ['text' => ucfirst($c->status), 'tone' => $c->status === 'sent' ? 'ok' : 'info'])</td></tr>
            @empty <tr><td class="empty">No campaigns yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
