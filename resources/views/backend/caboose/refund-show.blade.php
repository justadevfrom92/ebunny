@extends('backend.layouts.app')

@section('crumb', 'Refund #'.$r->id)

{{-- One refund: the request, where it is (requested → approved → paid), the account, and the next step --}}
@section('content')
    @php $tone = ['requested' => 'info', 'approved' => 'ok', 'paid' => 'ok', 'rejected' => 'bad'][$r->status] ?? ''; @endphp
    @include('backend.partials.page-head', ['title' => 'Refund #'.$r->id.' · $'.number_format($r->amount, 2),
        'sub' => e(ucfirst($r->kind).' refund by '.$r->method).' · '.view('backend.partials.pill', ['text' => ucfirst($r->status), 'tone' => $tone])->render(),
        'actions' => '<a class="btn ghost" href="'.route('caboose.refunds').'">All Refunds</a>'])

    <div class="panel"><div class="panel-head"><h2>Progress</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Step</th><th>When</th><th>Who</th><th>Detail</th></tr></thead>
        <tbody>
            <tr><td><b>Requested</b></td><td>{{ $r->created_at->format('n/j/Y g:i A') }}</td><td>{{ $r->requester?->name ?? 'System' }}</td><td class="wrap">{{ $r->reason }}</td></tr>
            <tr><td><b>{{ $r->status === 'rejected' ? 'Rejected' : 'Approved' }}</b></td><td>{{ $r->decided_at?->format('n/j/Y g:i A') ?? 'Waiting' }}</td><td>{{ $r->decider?->name ?? '—' }}</td><td class="wrap">{{ $r->decision_note ?? ($r->decided_at ? '—' : 'Needs someone with the refunds right') }}</td></tr>
            @if ($r->status !== 'rejected')<tr><td><b>Paid</b></td><td>{{ $r->paid_at?->format('n/j/Y g:i A') ?? 'Waiting' }}</td><td>{{ $r->paid_at ? 'Finance' : '—' }}</td><td>{{ $r->paid_at ? $r->method.($r->check_number ? ' #'.$r->check_number : '') : 'Sent once approved' }}</td></tr>@endif
        </tbody>
    </table></div></div>

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Refund</h2></div><div class="panel-body"><dl class="kv">
            <dt>Amount</dt><dd><b>${{ number_format($r->amount, 2) }}</b></dd>
            <dt>Of</dt><dd>{{ $r->kind === 'deposit' ? 'Deposit held' : 'Credit balance' }}</dd>
            <dt>Method</dt><dd>{{ $r->method }}</dd>
            <dt>Reason</dt><dd>{{ $r->reason }}</dd>
            <dt>Status</dt><dd>{{ ucfirst($r->status) }}</dd>
            @if ($r->check_number)<dt>Check #</dt><dd class="mono">{{ $r->check_number }}</dd>@endif
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Account</h2></div><div class="panel-body"><dl class="kv">
            @if ($c)
                <dt>Account</dt><dd>@include('backend.partials.account-link', ['c' => $c])</dd>
                <dt>Name</dt><dd>{{ $c->name }}</dd>
                <dt>Status</dt><dd>{{ $c->status }}</dd>
                <dt>Balance</dt><dd>{{ $c->balance < 0 ? '-' : '' }}${{ number_format(abs($c->balance), 2) }}{{ $c->balance < 0 ? ' (credit)' : '' }}</dd>
                <dt>Deposit Held</dt><dd>${{ number_format($c->deposit_held, 2) }}</dd>
                <dt>Can Refund Now</dt><dd>${{ number_format($limit, 2) }} {{ $r->kind === 'deposit' ? 'of deposit' : 'of credit' }}</dd>
            @else <dt>Account</dt><dd>Removed</dd> @endif
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Next Step</h2></div><div class="panel-body">
            @if (in_array($r->status, ['requested', 'approved'], true))
                @can('refunds')
                    <form method="post" action="{{ route('caboose.refunds.decide', $r) }}" class="stack">@csrf
                        @if ($r->status === 'requested')
                            <div class="field"><label for="note">Note (optional)</label><input id="note" name="note" maxlength="250" placeholder="e.g. Final bill confirmed"></div>
                            <div class="actions"><button class="btn cyan" name="do" value="approve">Approve</button><button class="btn ghost" name="do" value="reject">Reject</button></div>
                        @else
                            <div class="field"><label for="check_number">{{ $r->method === 'Check' ? 'Check number' : 'Confirmation #' }}</label><input id="check_number" name="check_number" maxlength="30" class="mono"></div>
                            <div class="actions"><button class="btn cyan" name="do" value="paid">Mark Paid</button></div>
                            <p class="help">Marking paid takes ${{ number_format($r->amount, 2) }} off the {{ $r->kind === 'deposit' ? 'deposit held' : 'credit balance' }}.</p>
                        @endif
                    </form>
                @else <p class="muted">Approving and paying refunds needs the refunds right.</p> @endcan
            @else
                <p>{{ $r->status === 'paid' ? 'Sent '.$r->paid_at?->format('n/j/Y').'. Nothing left to do.' : 'Rejected. Request a new refund if anything changes.' }}</p>
            @endif
        </div></div>
    </div>

    <div @class(['grid-2' => $others->isNotEmpty()])>
        @if ($others->isNotEmpty())<div class="panel"><div class="panel-head"><h2>Other Refunds on This Account</h2></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Requested</th><th>Kind</th><th class="num">Amount</th><th>Status</th></tr></thead>
            <tbody>@forelse ($others as $o)<tr class="click" data-href="{{ route('caboose.refunds.show', $o) }}"><td class="nowrap"><a href="{{ route('caboose.refunds.show', $o) }}"><b>#{{ $o->id }}</b></a></td><td>{{ $o->created_at->format('n/j/Y') }}</td><td>{{ ucfirst($o->kind) }}</td><td class="num">${{ number_format($o->amount, 2) }}</td><td>{{ ucfirst($o->status) }}</td></tr>
            @empty <tr><td colspan="5" class="empty">This is the first refund for this account.</td></tr> @endforelse</tbody>
        </table></div></div>@endif
        <div class="panel"><div class="panel-head"><h2>Recent Payments</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Paid</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr></thead>
            <tbody>@forelse ($payments as $p)<tr><td>{{ $p->paid_on?->format('n/j/Y') }}</td><td>{{ $p->method }} · {{ $p->source }}</td><td class="num">${{ number_format($p->amount, 2) }}</td><td>@include('backend.partials.pill', ['text' => $p->status, 'tone' => $p->status === 'Success' ? 'ok' : 'bad'])</td></tr>
            @empty <tr><td colspan="4" class="empty">No payments yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>

    @if ($notes->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Account Notes</h2><span class="muted">about refunds</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($notes as $n)<tr><td class="nowrap">{{ $n->created_at->format('n/j/Y g:i A') }}</td><td>{{ $n->author }}</td><td class="wrap">{{ $n->body }}</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
