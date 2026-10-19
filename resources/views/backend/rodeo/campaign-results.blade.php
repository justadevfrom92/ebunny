@extends('backend.layouts.app')

@section('crumb', $campaign->name)

@section('content')
    @php
        $a = $campaign->audience ?? [];
        $base = max(1, $r['sent']);
        $steps = array_filter(['Sent' => $r['sent'], 'Delivered' => $r['delivered'], 'Opened' => $r['opened'], 'Clicked' => $r['clicked'],
            'Converted' => $campaign->goal === 'announcement' ? null : $r['converted']], fn ($v) => $v !== null);
        $conv = \App\Models\Campaign::GOALS[$campaign->goal][1] ?? null;
    @endphp
    @include('backend.partials.page-head', ['title' => $campaign->name,
        'sub' => 'Sent '.$campaign->sent_at->format('l, F j, Y g:i A').' · '.$campaign->goalLabel().' · '.($campaign->channel === 'SMS' ? 'Text message' : 'Email: '.e($campaign->template?->name ?? '—')),
        'actions' => '<a class="btn ghost" href="'.route('rodeo.campaigns').'">All Campaigns</a><a class="btn ghost" href="'.route('rodeo.campaigns.heatmap', $campaign).'">Click Heat Map</a><form method="post" action="'.route('rodeo.campaigns.duplicate', $campaign).'" class="inline">'.csrf_field().'<button class="btn cyan">Duplicate</button></form>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Recipients</span><strong>{{ number_format($r['sent']) }}</strong><small>{{ number_format($campaign->skipped) }} skipped (suppressed or contacted recently)</small></div>
        <div class="stat"><span>Delivered</span><strong>{{ round(100 * $r['delivered'] / $base) }}%</strong><small>{{ number_format($r['delivered']) }} of {{ number_format($r['sent']) }}</small></div>
        <div class="stat"><span>{{ $campaign->channel === 'Email' ? 'Opened' : 'Channel' }}</span><strong>{{ $campaign->channel === 'Email' ? round(100 * $r['opened'] / $base).'%' : 'Text' }}</strong><small>{{ $campaign->channel === 'Email' ? number_format($r['clicked']).' clicked ('.round(100 * $r['clicked'] / $base).'%)' : 'texts have no open tracking' }}</small></div>
        <div class="stat"><span>Converted</span><strong>{{ $conv ? round(100 * $r['converted'] / $base, 1).'%' : '—' }}</strong><small>{{ $conv ? number_format($r['converted']).' '.$conv.' within '.\App\Models\Campaign::WINDOW.' days' : 'announcements have no goal to count' }}</small></div>
    </div>

    <div class="grid-2" style="align-items:start;margin-bottom:20px">
        <div class="panel"><div class="panel-head"><h2>Funnel</h2><span class="muted">share of everyone it was sent to</span></div><div class="panel-body">
            <div class="sv-bars">@foreach ($steps as $label => $n)
                <div class="sv-bar" tabindex="0" data-tip="{{ $label }}: {{ number_format($n) }} ({{ round(100 * $n / $base, 1) }}%)"><span>{{ $label }}</span><span class="track"><span class="fill" style="width:{{ round(100 * $n / $base) }}%"></span></span><span class="n">{{ number_format($n) }}</span></div>
            @endforeach</div>
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Details</h2></div><div class="panel-body"><dl class="kv">
            <dt>Goal</dt><dd>{{ $campaign->goalLabel() }}@if ($campaign->product): {{ $campaign->product }}@endif</dd>
            @if ($campaign->description)<dt>Notes</dt><dd>{{ $campaign->description }}</dd>@endif
            <dt>Audience</dt><dd>{{ collect([implode(', ', $a['statuses'] ?? []) ?: null, $a['type'] ?? null, isset($a['autopay']) ? 'AutoPay '.($a['autopay'] === 'yes' ? 'on' : 'off') : null,
                isset($a['balance']) ? ($a['balance'] === 'owes' ? 'owes money' : 'paid up') : null, isset($a['product']) ? 'has '.$a['product'] : null, isset($a['without_product']) ? 'no '.$a['without_product'] : null,
                isset($a['contract_ends_within']) ? 'contract ends within '.$a['contract_ends_within'].' days' : null])->filter()->implode(' · ') ?: 'Every opted-in customer' }}</dd>
            <dt>Tracking</dt><dd>{{ $campaign->msid ? 'MSID '.$campaign->msid : 'No MSID' }} · {{ $campaign->promo_code ? 'promo '.$campaign->promo_code : 'no promo code' }}</dd>
            <dt>Contact limit</dt><dd>{{ $campaign->cap_days ? 'skipped anyone contacted in the previous '.$campaign->cap_days.' days' : 'none' }}</dd>
        </dl></div></div>
    </div>

    @if ($daily->isNotEmpty())
        <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Opens by Day</h2></div><div class="panel-body">
            <x-bar-chart :bars="$daily->map(fn ($n, $d) => ['label' => \Illuminate\Support\Carbon::parse($d)->format('n/j'), 'value' => $n, 'tip' => \Illuminate\Support\Carbon::parse($d)->format('D n/j')])->values()" unit="opens" :height="150" />
        </div></div>
    @endif

    <div class="panel"><div class="panel-head"><h2>Recipients</h2><span class="muted">{{ number_format($recipients->total()) }}</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Account</th><th>Status</th>@if ($campaign->channel === 'Email')<th>Opened</th><th>Clicked</th>@endif<th>Result</th></tr></thead>
            <tbody>@foreach ($recipients as $m)
                <tr>
                    <td>@if ($m->customer)@include('backend.partials.account-link', ['c' => $m->customer])<br><span class="help">{{ $m->customer->name }}</span>@endif</td>
                    <td>{{ ucfirst($m->dropped_at ? 'bounced' : $m->status) }}</td>
                    @if ($campaign->channel === 'Email')<td>{{ $m->opened_at?->format('n/j g:i A') ?? '—' }}</td><td>{{ $m->clicked_at?->format('n/j g:i A') ?? '—' }}</td>@endif
                    <td>@if ($convertedIds->has($m->customer_id))@include('backend.partials.pill', ['text' => 'Converted', 'tone' => 'ok'])@else<span class="muted">—</span>@endif</td></tr>
            @endforeach</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $recipients])
    </div>
@endsection
