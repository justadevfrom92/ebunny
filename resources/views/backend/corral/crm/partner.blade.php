@extends('backend.layouts.app')

@section('crumb', $p->name)

@section('content')
    @php $m = fn ($v) => '$'.number_format($v, 0); @endphp
    @include('backend.partials.page-head', ['title' => $p->name, 'sub' => e($p->kind).' · '.view('backend.partials.pill', ['text' => $p->status, 'tone' => ['Active' => 'ok', 'Paused' => 'warn'][$p->status] ?? ''])->render(),
        'actions' => '<a class="btn ghost" href="'.route('corral.crm.partners').'">All Partners</a><a class="btn ghost" href="'.route('corral.crm.leads.create', ['partner' => $p->id]).'">Add a Lead</a><a class="btn cyan" href="'.route('corral.crm.partners.edit', $p).'">Edit</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Leads Sent</span><strong>{{ $p->leads->count() }}</strong><small>{{ $p->leads->filter->isOpen()->count() }} still open</small></div>
        <div class="stat"><span>Won</span><strong>{{ $won->count() }}</strong><small>{{ $p->leads->whereIn('stage', ['won', 'lost'])->count() ? round(100 * $won->count() / $p->leads->whereIn('stage', ['won', 'lost'])->count()) : 0 }}% of closed leads</small></div>
        <div class="stat"><span>Open Pipeline</span><strong>{{ $m($pipeline) }}</strong><small>yearly value</small></div>
        <div class="stat"><span>Commission Earned</span><strong>${{ number_format($earned, 2) }}</strong><small>{{ $p->commissionLabel() }}</small></div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Contact</h2></div><div class="panel-body"><dl class="kv">
            <dt>Contact</dt><dd>{{ $p->contact_name ?? '—' }}</dd>
            <dt>Email</dt><dd>@if ($p->email)<a href="mailto:{{ $p->email }}">{{ $p->email }}</a>@else — @endif</dd>
            <dt>Phone</dt><dd>{{ $p->phone ?? '—' }}</dd>
            <dt>City</dt><dd>{{ $p->city ?? '—' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Agreement</h2></div><div class="panel-body"><dl class="kv">
            <dt>Commission</dt><dd>{{ $p->commissionLabel() }}</dd>
            <dt>Agreement Ends</dt><dd>{{ $p->agreement_ends_on?->format('n/j/Y') ?? 'No end date' }}</dd>
            <dt>Our Contact</dt><dd>{{ $p->owner?->name ?? '—' }}</dd>
            <dt>Notes</dt><dd>{{ $p->notes ?? '—' }}</dd>
        </dl></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Leads From {{ $p->name }}</h2><span class="muted">{{ $p->leads->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Lead</th><th>Stage</th><th class="num">Yearly Value</th><th class="num">kWh / Year</th><th class="num">Commission</th><th>Owner</th><th>Added</th></tr></thead>
        <tbody>@forelse ($p->leads as $l)<tr class="click" data-href="{{ route('corral.crm.leads.show', $l) }}"><td><a href="{{ route('corral.crm.leads.show', $l) }}">{{ $l->displayName() }}</a></td><td>@include('backend.partials.pill', ['text' => $l->stageLabel(), 'tone' => $l->stageTone()])</td>
            <td class="num">{{ $m($l->est_value) }}</td><td class="num">{{ number_format($l->annual_kwh) }}</td><td class="num">{{ $l->stage === 'won' ? '$'.number_format($p->commissionFor($l), 2) : '—' }}</td><td>{{ $l->owner?->name }}</td><td>{{ $l->created_at->format('n/j/Y') }}</td></tr>
        @empty <tr><td colspan="7" class="empty">No leads yet.</td></tr> @endforelse</tbody>
    </table></div></div>

    <form method="post" action="{{ route('corral.crm.partners.activity', $p) }}" class="panel">@csrf
        <div class="panel-head"><h2>Log Activity</h2></div>
        <div class="panel-body stack">
            <div class="chips small">@foreach (\App\Models\CrmActivity::TYPES as $t => [$label])<label><input type="radio" name="type" value="{{ $t }}" @checked(old('type', 'call') === $t)><span>{{ $label }}</span></label>@endforeach</div>
            <div class="grid-2"><div class="field"><label for="subject">What happened</label><input id="subject" name="subject" required maxlength="200"></div>
                <div class="field"><label for="due_on">Follow up on</label><input id="due_on" name="due_on" type="date"></div></div>
            <div class="field"><label for="body">Details</label><textarea id="body" name="body" rows="2" maxlength="2000"></textarea></div>
            <div><button class="btn cyan">Log It</button></div>
        </div>
    </form>

    <div class="panel"><div class="panel-head"><h2>Activity</h2></div><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Type</th><th>What</th><th>By</th></tr></thead>
        <tbody>@forelse ($p->activities as $a)<tr><td class="nowrap">{{ $a->created_at->format('n/j/Y g:i A') }}</td><td>{{ $a->typeLabel() }}</td><td class="wrap"><b>{{ $a->subject }}</b>@if ($a->body)<div class="help">{{ $a->body }}</div>@endif @if ($a->due_on)<div class="help">Due {{ $a->due_on->format('n/j/Y') }}{{ $a->done_at ? ' · done' : '' }}</div>@endif</td><td>{{ $a->user?->name }}</td></tr>
        @empty <tr><td colspan="4" class="empty">No activity yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
