@extends('backend.layouts.app')

@section('crumb', ucfirst($e->kind).' #'.$e->id)

{{-- One credit or debit: what it is, what it does to the balance, the account's history, and Apply / Reject --}}
@section('content')
    @php $pending = $e->status === 'pending'; $tone = ['pending' => 'info', 'applied' => 'ok'][$e->status] ?? ''; @endphp
    @include('backend.partials.page-head', ['title' => ucfirst($e->kind).' #'.$e->id.' · $'.number_format($e->amount, 2),
        'sub' => e($e->description).' · '.view('backend.partials.pill', ['text' => $e->status === 'deleted' ? 'Rejected' : ucfirst($e->status), 'tone' => $tone])->render(),
        'actions' => '<a class="btn ghost" href="'.route('caboose.ledger').'">All Credits &amp; Debits</a>'])

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>{{ ucfirst($e->kind) }}</h2></div><div class="panel-body"><dl class="kv">
            <dt>Kind</dt><dd>{{ ucfirst($e->kind) }} ({{ $e->kind === 'credit' ? 'lowers' : 'raises' }} the balance)</dd>
            <dt>Amount</dt><dd><b>${{ number_format($e->amount, 2) }}</b></dd>
            <dt>Description</dt><dd>{{ $e->description }}</dd>
            <dt>Added</dt><dd>{{ $e->created_at->format('n/j/Y g:i A') }} ({{ $e->created_at->diffForHumans() }})</dd>
            <dt>Added By</dt><dd>{{ $e->user?->name ?? 'System (rewards, promos, products)' }}</dd>
            <dt>Status</dt><dd>{{ $e->status === 'deleted' ? 'Rejected' : ucfirst($e->status) }}</dd>
            @unless ($pending)
                <dt>Decided</dt><dd>{{ ($e->decided_at ?? $e->updated_at)->format('n/j/Y g:i A') }}</dd>
                <dt>Decided By</dt><dd>{{ $e->decider?->name ?? '—' }}</dd>
                <dt>Note</dt><dd>{{ $e->decision_note ?? '—' }}</dd>
            @endunless
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Account</h2></div><div class="panel-body"><dl class="kv">
            @if ($c)
                <dt>Account</dt><dd class="mono">{{ $c->account }}</dd>
                <dt>Name</dt><dd>{{ $c->name }}</dd>
                <dt>Status</dt><dd>{{ $c->status }}</dd>
                <dt>Balance Now</dt><dd>{{ $c->balance < 0 ? '-' : '' }}${{ number_format(abs($c->balance), 2) }}{{ $c->balance < 0 ? ' (credit)' : '' }}</dd>
                @if ($pending)<dt>After Applying</dt><dd><b>{{ $after < 0 ? '-' : '' }}${{ number_format(abs($after), 2) }}</b>{{ $after < 0 ? ' (credit)' : '' }}</dd>@endif
                <dt>Deposit Held</dt><dd>${{ number_format($c->deposit_held, 2) }}</dd>
                <dt>AutoPay</dt><dd>{{ $c->autopay ? 'On' : 'Off' }}</dd>
            @else <dt>Account</dt><dd>Removed</dd> @endif
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Decision</h2></div><div class="panel-body">
            @if ($pending)
                <form method="post" action="{{ route('caboose.ledger.decide', $e) }}" class="stack">@csrf
                    <div class="field"><label for="note">Note (optional)</label><input id="note" name="note" maxlength="250" placeholder="e.g. Matches the product order"></div>
                    <div class="actions"><button class="btn cyan" name="do" value="apply">Apply to Balance</button><button class="btn ghost" name="do" value="reject">Reject</button></div>
                    <p class="help">Applying {{ $e->kind === 'credit' ? 'takes' : 'adds' }} ${{ number_format($e->amount, 2) }} {{ $e->kind === 'credit' ? 'off' : 'to' }} the balance and leaves a note on the account.</p>
                </form>
            @else
                <p>{{ $e->status === 'applied' ? 'Applied to the balance' : 'Rejected' }} by {{ $e->decider?->name ?? 'finance' }} on {{ ($e->decided_at ?? $e->updated_at)->format('n/j/Y') }}.</p>
            @endif
        </div></div>
    </div>

    @if ($others->isNotEmpty())<div class="panel"><div class="panel-head"><h2>Other Credits &amp; Debits on This Account</h2><span class="muted">{{ $others->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Added</th><th>Kind</th><th class="num">Amount</th><th>Description</th><th>By</th><th>Status</th></tr></thead>
        <tbody>@forelse ($others as $o)<tr class="click" data-href="{{ route('caboose.ledger.show', $o) }}"><td><a href="{{ route('caboose.ledger.show', $o) }}">{{ $o->created_at->format('n/j/Y') }}</a></td><td>{{ ucfirst($o->kind) }}</td><td class="num">${{ number_format($o->amount, 2) }}</td><td>{{ $o->description }}</td><td>{{ $o->user?->name ?? 'System' }}</td><td>@include('backend.partials.pill', ['text' => $o->status === 'deleted' ? 'Rejected' : ucfirst($o->status), 'tone' => ['pending' => 'info', 'applied' => 'ok'][$o->status] ?? ''])</td></tr>
        @empty <tr><td colspan="6" class="empty">This is the only one.</td></tr> @endforelse</tbody>
    </table></div></div>@endif

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Recent Bills</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Billed</th><th>Invoice</th><th class="num">Amount</th><th class="num">Balance After</th></tr></thead>
            <tbody>@forelse ($bills as $b)<tr><td>{{ $b->billed_on?->format('n/j/Y') }}</td><td class="mono">{{ $b->invoice ?? $b->reference }}</td><td class="num">${{ number_format($b->amount, 2) }}</td><td class="num">${{ number_format($b->balance_after, 2) }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No bills yet.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Recent Payments</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Paid</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr></thead>
            <tbody>@forelse ($payments as $p)<tr><td>{{ $p->paid_on?->format('n/j/Y') }}</td><td>{{ $p->method }} · {{ $p->source }}</td><td class="num">${{ number_format($p->amount, 2) }}</td><td>@include('backend.partials.pill', ['text' => $p->status, 'tone' => $p->status === 'Success' ? 'ok' : 'bad'])</td></tr>
            @empty <tr><td colspan="4" class="empty">No payments yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>

    @if ($notes->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Account Notes</h2><span class="muted">about credits and debits</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($notes as $n)<tr><td class="nowrap">{{ $n->created_at->format('n/j/Y g:i A') }}</td><td>{{ $n->author }}</td><td class="wrap">{{ $n->body }}</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
