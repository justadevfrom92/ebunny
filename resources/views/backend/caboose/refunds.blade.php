@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Refunds', 'sub' => 'Refunds of credit balances and deposits. Anyone in Caboose can request one; approving needs the refunds right.',
        'actions' => '<a class="btn cyan" href="'.route('caboose.refunds.create').'">Request a Refund</a>'])
    <div class="stack">
        <div class="panel"><div class="panel-head"><h2>Open</h2><span class="muted">{{ $open->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Requested</th><th>Account</th><th>Kind</th><th class="num">Amount</th><th>Reason</th><th>Method</th><th>Status</th><th></th></tr></thead>
            <tbody>@forelse ($open as $r)
                <tr class="click" data-href="{{ route('caboose.refunds.show', $r) }}"><td class="nowrap"><a href="{{ route('caboose.refunds.show', $r) }}"><b>#{{ $r->id }}</b></a></td><td>{{ $r->created_at->format('n/j/Y') }}<br><span class="help">{{ $r->requester?->name }}</span></td><td>@include('backend.partials.account-link', ['c' => $r->customer])<br><span class="help">{{ $r->customer?->name }}</span></td>
                    <td>{{ ucfirst($r->kind) }}</td><td class="num">${{ number_format($r->amount, 2) }}</td><td class="wrap">{{ $r->reason }}</td><td>{{ $r->method }}</td>
                    <td>@include('backend.partials.pill', ['text' => ucfirst($r->status), 'tone' => $r->status === 'approved' ? 'ok' : 'info'])</td>
                    <td class="actions">@can('refunds')
                        @if ($r->status === 'requested')
                            @foreach (['approve' => 'Approve', 'reject' => 'Reject'] as $do => $l)<form method="post" action="{{ route('caboose.refunds.decide', $r) }}" class="inline">@csrf<input type="hidden" name="do" value="{{ $do }}"><button class="btn sm {{ $do === 'approve' ? '' : 'ghost' }}">{{ $l }}</button></form>@endforeach
                        @else
                            <form method="post" action="{{ route('caboose.refunds.decide', $r) }}" class="inline" data-confirm="Mark refund paid?|Confirm the {{ strtolower($r->method) }} for ${{ number_format($r->amount, 2) }} was sent.|Mark Paid">@csrf<input type="hidden" name="do" value="paid"><button class="btn sm">Mark Paid</button></form>
                        @endif
                    @else <span class="help">Needs refunds right</span> @endcan</td></tr>
            @empty <tr><td colspan="9" class="empty">No open refunds.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Credit Balances Without a Refund</h2><span class="muted">{{ $credits->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Account</th><th>Name</th><th>Status</th><th class="num">Credit</th><th></th></tr></thead>
            <tbody>@forelse ($credits as $c)<tr><td>@include('backend.partials.account-link', ['c' => $c])</td><td>{{ $c->name }}</td><td>{{ $c->status }}</td><td class="num">${{ number_format(abs($c->balance), 2) }}</td><td class="actions"><a class="btn sm ghost" href="{{ route('caboose.refunds.create', ['account' => $c->account, 'amount' => number_format(abs($c->balance), 2, '.', '')]) }}">Request Refund</a></td></tr>
            @empty <tr><td colspan="5" class="empty">None.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Recently Closed</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Closed</th><th>Account</th><th>Kind</th><th class="num">Amount</th><th>Status</th><th>Decided By</th></tr></thead>
            <tbody>@forelse ($done as $r)<tr class="click" data-href="{{ route('caboose.refunds.show', $r) }}"><td><a href="{{ route('caboose.refunds.show', $r) }}">{{ ($r->paid_at ?? $r->decided_at ?? $r->updated_at)->format('n/j/Y') }}</a></td><td>@include('backend.partials.account-link', ['c' => $r->customer])</td><td>{{ ucfirst($r->kind) }}</td><td class="num">${{ number_format($r->amount, 2) }}</td><td>@include('backend.partials.pill', ['text' => ucfirst($r->status), 'tone' => $r->status === 'paid' ? 'ok' : ''])</td><td>{{ $r->decider?->name }}</td></tr>
            @empty <tr><td colspan="6" class="empty">None yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
