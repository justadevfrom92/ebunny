@extends('backend.layouts.app')

@section('crumb', 'Lead Approval #'.$a->id)

@section('content')
    @include('backend.partials.page-head', ['title' => 'Lead Approval #'.$a->id.' · '.$a->label(), 'sub' => ['pending' => 'Waiting for a Lead', 'approved' => 'Approved', 'denied' => 'Denied'][$a->status],
        'actions' => '<a class="btn ghost" href="'.route('corral.approvals').'">Back to Lead Approvals</a>'])

    @if ($isLead && $a->status === 'pending')
        <div class="panel secure-panel"><div class="secure-head"><h2>Approve or Deny</h2></div>
            <div class="secure-actions">
                <form method="post" action="{{ route('corral.approvals.approve', $a) }}" class="secure-opt" data-confirm="Approve request #{{ $a->id }}?|{{ $a->label() }} is done now, under your name.|Approve">@csrf
                    <h3>Approve</h3><p>{{ $a->label() }}@if ($a->details()) · {{ $a->details() }}@endif</p>
                    <div class="field"><label for="ap-note">Note (optional)</label><input id="ap-note" name="decision_note" maxlength="500"></div>
                    <button class="btn cyan">Approve</button>
                </form>
                <form method="post" action="{{ route('corral.approvals.deny', $a) }}" class="secure-opt">@csrf
                    <h3>Deny</h3><p>Say why, so they know what to tell the customer.</p>
                    <div class="field"><label for="dn-note">Why</label><input id="dn-note" name="decision_note" maxlength="500" required value="{{ old('decision_note') }}">
                        @error('decision_note')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                    <button class="btn danger">Deny</button>
                </form>
            </div>
        </div>
    @endif

    <div class="panel"><div class="panel-head"><h2>Request</h2><span class="mono muted">#{{ $a->id }}</span></div><div class="panel-body"><dl class="kv">
        <dt>Account</dt><dd>@if ($a->customer)<a href="{{ route('corral.customers.show', $a->customer) }}">{{ $a->customer->account }}</a> · {{ $a->customer->name }}@else — @endif</dd>
        <dt>Change</dt><dd><b>{{ $a->label() }}</b></dd>
        @if ($a->action === 'ledger-delete')
            <dt>Item</dt><dd>{{ $a->details() ?: '—' }}</dd>
        @else
            @foreach ($a->input ?? [] as $k => $v)<dt>{{ ucfirst(str_replace('_', ' ', $k)) }}</dt><dd>{{ $k === 'amount' ? '$'.number_format((float) $v, 2) : $v }}</dd>@endforeach
        @endif
        <dt>Asked By</dt><dd>{{ $a->requester?->name ?? '—' }} · {{ $a->created_at->format('n/j/Y g:i A') }}</dd>
        @if ($a->note)<dt>Why</dt><dd>{{ $a->note }}</dd>@endif
        <dt>Status</dt><dd>@include('backend.partials.pill', ['text' => ['pending' => 'Waiting', 'approved' => 'Approved', 'denied' => 'Denied'][$a->status], 'tone' => ['pending' => 'warn', 'approved' => 'ok', 'denied' => 'bad'][$a->status]])</dd>
        @if ($a->decided_at)
            <dt>{{ ucfirst($a->status) }} By</dt><dd>{{ $a->decider?->name ?? '—' }} · {{ $a->decided_at->format('n/j/Y g:i A') }}</dd>
            @if ($a->decision_note)<dt>Note</dt><dd>{{ $a->decision_note }}</dd>@endif
            @if ($a->result)<dt>Result</dt><dd>{{ $a->result }}</dd>@endif
        @endif
    </dl></div></div>
@endsection
