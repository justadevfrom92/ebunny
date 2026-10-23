@extends('backend.layouts.app')

@section('crumb', $lead->displayName())

{{-- One lead: who they are, where they are in the pipeline, every call/email/meeting, and what's next --}}
@section('content')
    @php $m = fn ($v) => '$'.number_format($v, 0); @endphp
    @include('backend.partials.page-head', ['title' => $lead->displayName(),
        'sub' => e($lead->kind).($lead->company ? ' · '.e($lead->name).($lead->title ? ', '.e($lead->title) : '') : '').' · '.view('backend.partials.pill', ['text' => $lead->stageLabel(), 'tone' => $lead->stageTone()])->render(),
        'actions' => '<a class="btn ghost" href="'.route('corral.crm.leads').'">All Leads</a><a class="btn cyan" href="'.route('corral.crm.leads.edit', $lead).'">Edit Lead</a>'])

    {{-- Stage: pick where it is now --}}
    <form method="post" action="{{ route('corral.crm.leads.stage', $lead) }}" class="panel" id="stage-form">@csrf
        <div class="panel-head"><h2>Stage</h2><span class="muted">since {{ ($lead->stage_changed_at ?? $lead->created_at)->format('n/j/Y') }}</span></div>
        <div class="panel-body stack">
            <div class="field" style="max-width:320px"><label for="stage">Stage</label><select id="stage" name="stage">@foreach (\App\Models\CrmLead::STAGES as $s => [$label, $chance])<option value="{{ $s }}" @selected($lead->stage === $s)>{{ $label }} ({{ $s === 'lost' ? 'closed' : $chance.'% chance' }})</option>@endforeach</select></div>
            <div class="grid-2" data-stage-extra>
                <div class="field" data-for="lost"><label for="lost_reason">Why it was lost</label><select id="lost_reason" name="lost_reason"><option value="">Pick a reason</option>@foreach (\App\Models\CrmLead::LOST_REASONS as $r)<option @selected(old('lost_reason', $lead->lost_reason) === $r)>{{ $r }}</option>@endforeach</select>
                    <input name="lost_other" maxlength="200" value="{{ in_array($lead->lost_reason, \App\Models\CrmLead::LOST_REASONS, true) ? '' : $lead->lost_reason }}" placeholder="Or type another reason" aria-label="Other reason" style="margin-top:8px"></div>
                <div class="field" data-for="won"><label for="account">New account number (optional)</label><input id="account" name="account" class="mono" maxlength="20" value="{{ old('account', $lead->customer?->account) }}" placeholder="1219…"></div>
            </div>
            <div><button class="btn">Save Stage</button></div>
        </div>
    </form>

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Contact</h2></div><div class="panel-body"><dl class="kv">
            <dt>Name</dt><dd>{{ $lead->name }}{{ $lead->title ? ', '.$lead->title : '' }}</dd>
            @if ($lead->company)<dt>Business</dt><dd>{{ $lead->company }}</dd>@endif
            <dt>Email</dt><dd>@if ($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@else — @endif</dd>
            <dt>Phone</dt><dd>@if ($lead->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->phone) }}">{{ $lead->phone }}</a>@else — @endif</dd>
            <dt>City</dt><dd>{{ $lead->city ?? '—' }}{{ $lead->market ? ' · '.$lead->market->name : '' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Opportunity</h2></div><div class="panel-body"><dl class="kv">
            <dt>Yearly Value</dt><dd><b>{{ $m($lead->est_value) }}</b> <span class="muted">· weighted {{ $m($lead->weighted()) }}</span></dd>
            <dt>Usage</dt><dd>{{ number_format($lead->annual_kwh) }} kWh a year{{ $lead->sites > 1 ? ' across '.$lead->sites.' sites' : '' }}</dd>
            <dt>Current Provider</dt><dd>{{ $lead->current_provider ?? '—' }}</dd>
            <dt>Their Contract Ends</dt><dd>{{ $lead->contract_ends_on?->format('n/j/Y') ?? '—' }}@if ($lead->contract_ends_on && $lead->contract_ends_on->isFuture()) <span class="muted">({{ $lead->contract_ends_on->diffForHumans() }})</span>@endif</dd>
            @if ($lead->stage === 'lost')<dt>Lost Because</dt><dd>{{ $lead->lost_reason }}</dd>@endif
            @if ($lead->customer)<dt>Account</dt><dd><a href="{{ route('corral.customers.show', $lead->customer) }}">{{ $lead->customer->account }}</a> · {{ $lead->customer->status }}</dd>@endif
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Ownership</h2></div><div class="panel-body"><dl class="kv">
            <dt>Owner</dt><dd>{{ $lead->owner?->name ?? 'Unassigned' }}</dd>
            <dt>Source</dt><dd>{{ $lead->source }}</dd>
            @if ($lead->partner)<dt>Partner</dt><dd><a href="{{ route('corral.crm.partners.show', $lead->partner) }}">{{ $lead->partner->name }}</a><div class="help">{{ $lead->partner->commissionLabel() }}{{ $lead->stage === 'won' ? ' · earns $'.number_format($lead->partner->commissionFor($lead), 2) : '' }}</div></dd>@endif
            <dt>Added</dt><dd>{{ $lead->created_at->format('n/j/Y') }} ({{ $lead->created_at->diffForHumans() }})</dd>
            <dt>Next Follow-up</dt><dd>@if ($lead->next_follow_up_on)<span @class(['bad-text' => $lead->followUpOverdue()])>{{ $lead->next_follow_up_on->format('l, n/j/Y') }}</span>@else — @endif</dd>
        </dl></div></div>
    </div>

    @if ($lead->notes)<div class="panel"><div class="panel-head"><h2>Notes</h2></div><div class="panel-body" style="white-space:pre-line">{{ $lead->notes }}</div></div>@endif

    @if ($open->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Open Follow-ups</h2><span class="muted">{{ $open->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Due</th><th>Follow-up</th><th>Set By</th><th></th></tr></thead>
            <tbody>@foreach ($open as $t)<tr><td class="nowrap"><span @class(['bad-text' => $t->due_on->lt(today())])>{{ $t->due_on->format('D n/j/Y') }}</span></td><td class="wrap"><b>{{ $t->subject }}</b>@if ($t->body)<div class="help">{{ $t->body }}</div>@endif</td><td>{{ $t->user?->name }}</td>
                <td class="actions"><form method="post" action="{{ route('corral.crm.tasks.complete', $t) }}" class="inline">@csrf<button class="btn sm">Done</button></form></td></tr>@endforeach</tbody>
        </table></div></div>
    @endif

    <form method="post" action="{{ route('corral.crm.leads.activity', $lead) }}" class="panel">@csrf
        <div class="panel-head"><h2>Log Activity</h2><span class="muted">a call, email, meeting or note, or set a follow-up</span></div>
        <div class="panel-body stack">
            <select name="type" id="sel-type" style="max-width:320px">@foreach (\App\Models\CrmActivity::TYPES as $t => [$label])<option value="{{ $t }}" @selected(old('type', 'call') === $t)>{{ $label }}</option>@endforeach</select>
            <div class="grid-2"><div class="field"><label for="subject">What happened</label><input id="subject" name="subject" required maxlength="200" value="{{ old('subject') }}" placeholder="e.g. Sent the 24-month business quote"></div>
                <div class="field"><label for="due_on">Follow up on</label><input id="due_on" name="due_on" type="date" value="{{ old('due_on') }}"><div class="help">Needed for a follow-up.</div></div></div>
            <div class="field"><label for="body">Details</label><textarea id="body" name="body" rows="3" maxlength="2000">{{ old('body') }}</textarea></div>
            <div><button class="btn cyan">Log It</button></div>
        </div>
    </form>

    <div class="panel"><div class="panel-head"><h2>Timeline</h2><span class="muted">{{ $lead->activities->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Type</th><th>What</th><th>By</th></tr></thead>
        <tbody>@foreach ($lead->activities as $a)<tr><td class="nowrap">{{ $a->created_at->format('n/j/Y g:i A') }}</td><td>@include('backend.partials.pill', ['text' => $a->typeLabel(), 'tone' => $a->type === 'stage' ? 'info' : ''])</td>
            <td class="wrap"><b>{{ $a->subject }}</b>@if ($a->body)<div class="help">{{ $a->body }}</div>@endif @if ($a->due_on)<div class="help">Due {{ $a->due_on->format('n/j/Y') }}{{ $a->done_at ? ' · done '.$a->done_at->format('n/j/Y') : '' }}</div>@endif</td><td>{{ $a->user?->name ?? 'System' }}</td></tr>@endforeach</tbody>
    </table></div></div>

    @if ($similar->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Existing Accounts With This Name</h2><span class="muted">check before creating an order</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($similar as $c)<tr class="click" data-href="{{ route('corral.customers.show', $c) }}"><td><a href="{{ route('corral.customers.show', $c) }}" class="mono">{{ $c->account }}</a></td><td>{{ $c->name }}</td><td>{{ $c->status }}</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif

    <script>
    (function () {
        var f = document.getElementById('stage-form'); if (!f) return;
        function sync() { var v = f.querySelector('[name=stage]').value; f.querySelectorAll('[data-for]').forEach(function (el) { el.hidden = el.dataset.for !== v; }); f.querySelector('[data-stage-extra]').hidden = v !== 'won' && v !== 'lost'; }
        f.addEventListener('change', sync); sync();
    })();
    </script>
@endsection
