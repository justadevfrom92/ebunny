@extends('backend.layouts.app')

@section('content')
    @php $m = fn ($v) => '$'.number_format($v, 0); $chip = fn ($key, $val, $label) => '<a href="'.route('corral.crm.leads', array_filter(array_merge($f, [$key => ($f[$key] ?? null) === $val ? null : $val]))).'" class="chip-link'.(($f[$key] ?? null) === $val ? ' on' : '').'">'.e($label).'</a>'; @endphp
    @include('backend.partials.page-head', ['title' => 'Leads', 'sub' => 'Everyone in the pipeline. Pick filters below; pick one again to clear it.',
        'actions' => '<a class="btn ghost" href="'.route('corral.crm.pipeline').'">Pipeline</a><a class="btn cyan" href="'.route('corral.crm.leads.create').'">New Lead</a>'])

    <div class="panel"><div class="panel-body stack">
        <form method="get" action="{{ route('corral.crm.leads') }}" class="inline-search">@foreach (array_diff_key($f, ['q' => 1]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Search name, business, email or phone" aria-label="Search leads"><button class="btn">Search</button></form>
        <div class="field"><label>Stage</label><div class="chips small">{!! $chip('stage', 'open', 'All open') !!}@foreach (\App\Models\CrmLead::STAGES as $s => [$label]){!! $chip('stage', $s, $label.' · '.($counts[$s] ?? 0)) !!}@endforeach</div></div>
        <div class="field"><label>Kind</label><div class="chips small">@foreach (\App\Models\CrmLead::KINDS as $k){!! $chip('kind', $k, $k) !!}@endforeach</div></div>
        <div class="field"><label>Source</label><div class="chips small">@foreach (\App\Models\CrmLead::SOURCES as $k){!! $chip('source', $k, $k) !!}@endforeach</div></div>
        <div class="field"><label>Owner</label><div class="chips small">{!! $chip('owner', 'me', 'Mine') !!}</div></div>
    </div></div>

    <div class="panel"><div class="panel-head"><h2>{{ number_format($leads->total()) }} {{ Str::plural('lead', $leads->total()) }}</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Lead</th><th>Kind</th><th>Stage</th><th class="num">Yearly Value</th><th>Owner</th><th>Source</th><th>Next Follow-up</th></tr></thead>
        <tbody>@forelse ($leads as $l)<tr class="click" data-href="{{ route('corral.crm.leads.show', $l) }}">
            <td><a href="{{ route('corral.crm.leads.show', $l) }}"><b>{{ $l->displayName() }}</b></a><div class="help">{{ $l->company ? $l->name.' · ' : '' }}{{ $l->city }}</div></td>
            <td>{{ $l->kind }}</td><td>@include('backend.partials.pill', ['text' => $l->stageLabel(), 'tone' => $l->stageTone()])</td>
            <td class="num">{{ $m($l->est_value) }}</td><td>{{ $l->owner?->name ?? '—' }}</td><td>{{ $l->source }}@if ($l->partner)<div class="help">{{ $l->partner->name }}</div>@endif</td>
            <td class="nowrap">@if (! $l->isOpen())<span class="muted">{{ $l->closed_at?->format('n/j/Y') }}</span>@elseif ($l->next_follow_up_on)<span @class(['bad-text' => $l->followUpOverdue()])>{{ $l->next_follow_up_on->format('n/j/Y') }}</span>@else<span class="muted">None set</span>@endif</td></tr>
        @empty <tr><td colspan="7" class="empty">No leads match.</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $leads])</div>
@endsection
