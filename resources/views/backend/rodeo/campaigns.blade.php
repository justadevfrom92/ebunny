@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Campaigns', 'sub' => 'Emails and texts to groups of customers, each with a goal so you can see what it achieved. Only customers who opted in to marketing are included.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.campaigns.create').'">New Campaign</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Scheduled</span><strong>{{ $scheduled->count() }}</strong><small>{{ $scheduled->first() ? 'next '.$scheduled->first()->scheduled_at->format('M j g:i A') : 'nothing queued' }}</small></div>
        <div class="stat"><span>Drafts</span><strong>{{ $drafts->count() }}</strong><small>not sent yet</small></div>
        <div class="stat"><span>Sent</span><strong>{{ $sent->count() }}</strong><small>{{ number_format($counts['sent30']) }} messages in 30 days</small></div>
        @php $conv = $sent->sum('converted_count'); $msgs = max(1, $sent->sum('messages_count')); @endphp
        <div class="stat"><span>Conversions</span><strong>{{ number_format($conv) }}</strong><small>{{ round(100 * $conv / $msgs, 1) }}% of recipients reached the goal</small></div>
    </div>

    @foreach (['Scheduled' => $scheduled, 'Drafts' => $drafts] as $heading => $list)
        <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>{{ $heading }} <span class="count-pill">{{ $list->count() }}</span></h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Campaign</th><th>Goal</th><th>Channel</th><th class="num">Audience</th><th>{{ $heading === 'Scheduled' ? 'Sends' : 'Last Edited' }}</th></tr></thead>
                <tbody>@forelse ($list as $c)
                    <tr class="click" data-href="{{ route('rodeo.campaigns.edit', $c) }}"><td><a href="{{ route('rodeo.campaigns.edit', $c) }}"><b>{{ $c->name }}</b></a>@if ($c->description)<br><span class="help">{{ \Illuminate\Support\Str::limit($c->description, 70) }}</span>@endif</td>
                        <td>{{ $c->goalLabel() }}@if ($c->product)<br><span class="help">{{ $c->product }}</span>@endif</td><td>{{ $c->channel === 'SMS' ? 'Text' : 'Email' }}</td>
                        <td class="num">{{ number_format($c->audienceQuery()->count()) }}</td>
                        <td class="nowrap">{{ $heading === 'Scheduled' ? $c->scheduled_at->format('D n/j g:i A') : $c->updated_at->diffForHumans() }}</td></tr>
                @empty <tr><td colspan="5" class="empty">{{ $heading === 'Scheduled' ? 'Nothing scheduled.' : 'No drafts.' }}</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    @endforeach

    <div class="panel"><div class="panel-head"><h2>Sent <span class="count-pill">{{ $sent->count() }}</span></h2><span class="muted">conversions count for {{ \App\Models\Campaign::WINDOW }} days after sending</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Campaign</th><th>Goal</th><th>Sent</th><th class="num">Recipients</th><th class="num">Opened</th><th class="num">Clicked</th><th class="num">Converted</th></tr></thead>
            <tbody>@forelse ($sent as $c)
                @php $pct = fn ($n) => $c->messages_count ? round(100 * $n / $c->messages_count).'%' : '—'; @endphp
                <tr class="click" data-href="{{ route('rodeo.campaigns.edit', $c) }}"><td><a href="{{ route('rodeo.campaigns.edit', $c) }}"><b>{{ $c->name }}</b></a><br><span class="help">{{ $c->channel === 'SMS' ? 'Text' : ($c->template?->name ?? 'Email') }}</span></td>
                    <td>{{ $c->goalLabel() }}</td><td class="nowrap">{{ $c->sent_at?->format('n/j/Y') }}</td><td class="num">{{ number_format($c->messages_count) }}</td>
                    <td class="num">{{ $c->channel === 'Email' ? $pct($c->opened_count) : '—' }}</td><td class="num">{{ $c->channel === 'Email' ? $pct($c->clicked_count) : '—' }}</td>
                    <td class="num">@if ($c->goal === 'announcement')—@else<b>{{ number_format($c->converted_count) }}</b> <span class="help">{{ $pct($c->converted_count) }}</span>@endif</td></tr>
            @empty <tr><td colspan="7" class="empty">Nothing sent yet.</td></tr> @endforelse</tbody>
        </table></div>
    </div>
@endsection
