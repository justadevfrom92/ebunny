@extends('backend.layouts.app')

{{-- CRM home: the open pipeline by stage, what it's worth, wins and where leads come from --}}
@section('content')
    @php $m = fn ($v) => '$'.number_format($v, 0); @endphp
    @include('backend.partials.page-head', ['title' => 'Sales Pipeline', 'sub' => 'Homes and businesses that might sign up, by stage. Weighted value counts each lead at its stage\'s chance of winning.',
        'actions' => '<a class="btn ghost" href="'.route('corral.crm.leads').'">All Leads</a><a class="btn cyan" href="'.route('corral.crm.leads.create').'">New Lead</a>'])

    <div class="chips small" style="margin-bottom:14px"><a href="{{ route('corral.crm.pipeline') }}" @class(['chip-link', 'on' => ! $mine])>Everyone</a><a href="{{ route('corral.crm.pipeline', ['owner' => 'me']) }}" @class(['chip-link', 'on' => $mine])>My leads</a></div>

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('corral.crm.leads', ['stage' => 'open']) }}"><span>Open Pipeline</span><strong>{{ $m($stats['value']) }}</strong><small>{{ $stats['open'] }} open {{ Str::plural('lead', $stats['open']) }} · yearly value</small></a>
        <div class="stat"><span>Weighted</span><strong>{{ $m($stats['weighted']) }}</strong><small>value × chance of winning</small></div>
        <a class="stat" href="{{ route('corral.crm.leads', ['stage' => 'won']) }}"><span>Won This Month</span><strong>{{ $stats['wonMonth'] }}</strong><small>{{ $m($stats['wonValue']) }} a year · {{ $stats['winRate'] }}% win rate (90 days)</small></a>
        <a class="stat" href="{{ route('corral.crm.tasks') }}"><span>Follow-ups Overdue</span><strong>{{ $stats['overdue'] }}</strong><small>leads past their follow-up date</small></a>
    </div>

    <div class="panel"><div class="panel-head"><h2>Stages</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Stage</th><th class="num">Leads</th><th class="num">Yearly Value</th><th class="num">Chance</th><th class="num">Weighted</th></tr></thead>
        <tbody>@foreach ($stages as $s => $list)<tr class="click" data-href="{{ route('corral.crm.leads', ['stage' => $s]) }}"><td><a href="{{ route('corral.crm.leads', ['stage' => $s]) }}">{{ \App\Models\CrmLead::STAGES[$s][0] }}</a></td><td class="num">{{ $list->count() }}</td><td class="num">{{ $m($list->sum('est_value')) }}</td><td class="num">{{ \App\Models\CrmLead::STAGES[$s][1] }}%</td><td class="num">{{ $m($list->sum(fn ($l) => $l->weighted())) }}</td></tr>@endforeach</tbody>
    </table></div></div>

    @foreach ($stages as $s => $list)
        <div class="panel"><div class="panel-head"><h2>{{ \App\Models\CrmLead::STAGES[$s][0] }}</h2><span class="muted">{{ $list->count() }} · {{ $m($list->sum('est_value')) }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Lead</th><th>Kind</th><th class="num">Yearly Value</th><th>Owner</th><th>Source</th><th>Next Follow-up</th></tr></thead>
            <tbody>@forelse ($list as $l)<tr class="click" data-href="{{ route('corral.crm.leads.show', $l) }}">
                <td><a href="{{ route('corral.crm.leads.show', $l) }}"><b>{{ $l->displayName() }}</b></a>@if ($l->company)<div class="help">{{ $l->name }}</div>@endif</td>
                <td>{{ $l->kind }}</td><td class="num">{{ $m($l->est_value) }}</td><td>{{ $l->owner?->name ?? '—' }}</td><td>{{ $l->source }}@if ($l->partner)<div class="help">{{ $l->partner->name }}</div>@endif</td>
                <td class="nowrap">@if ($l->next_follow_up_on)<span @class(['bad-text' => $l->followUpOverdue()])>{{ $l->next_follow_up_on->format('n/j/Y') }}</span>@else<span class="muted">None set</span>@endif</td></tr>
            @empty <tr><td colspan="6" class="empty">No leads at this stage.</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>New Leads by Source</h2><span class="muted">last 90 days</span></div><div class="panel-body"><x-bar-chart :bars="$bySource" unit="leads" /></div></div>
        <div class="panel"><div class="panel-head"><h2>Recent Wins</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Won</th><th>Lead</th><th class="num">Yearly Value</th><th>Owner</th></tr></thead>
            <tbody>@forelse ($recentWins as $l)<tr class="click" data-href="{{ route('corral.crm.leads.show', $l) }}"><td>{{ $l->closed_at?->format('n/j/Y') }}</td><td><a href="{{ route('corral.crm.leads.show', $l) }}">{{ $l->displayName() }}</a></td><td class="num">{{ $m($l->est_value) }}</td><td>{{ $l->owner?->name }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No wins yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
