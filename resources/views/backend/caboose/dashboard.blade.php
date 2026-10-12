@extends('backend.layouts.app')

@section('content')
    @php $m = fn ($v) => '$'.number_format($v, 2); @endphp
    @include('backend.partials.page-head', ['title' => 'Finance', 'sub' => 'Cash, receivables, deposits and the work waiting for finance.'])
    <div class="grid-4 stat-row">
        <div class="stat"><span>Collected Today</span><strong>{{ $m($today) }}</strong></div>
        <div class="stat"><span>Collected This Month</span><strong>{{ $m($month) }}</strong></div>
        <a class="stat" href="{{ route('caboose.aging') }}"><span>Accounts Receivable</span><strong>{{ $m($receivable) }}</strong><small>credit balances {{ $m(abs($credits)) }}</small></a>
        <a class="stat" href="{{ route('caboose.deposits') }}"><span>Deposits Held</span><strong>{{ $m($depositsHeld) }}</strong><small>{{ $m($depositsDue) }} due</small></a>
    </div>
    <div class="grid-3">
        <a class="panel report-card" href="{{ route('caboose.ledger') }}"><div class="panel-body"><h3>Pending Credits &amp; Debits</h3><p style="font-size:1.6rem"><b>{{ $ledger }}</b></p><p class="muted">waiting to be applied</p></div></a>
        <a class="panel report-card" href="{{ route('caboose.refunds') }}"><div class="panel-body"><h3>Refunds</h3><p style="font-size:1.6rem"><b>{{ $refunds }}</b></p><p class="muted">waiting for approval</p></div></a>
        <a class="panel report-card" href="{{ route('caboose.payments', ['status' => 'Pending']) }}"><div class="panel-body"><h3>Pending Payments</h3><p style="font-size:1.6rem"><b>{{ $pendingPayments }}</b></p><p class="muted">not yet processed</p></div></a>
    </div>
    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Payments by Method</h2><span class="muted">last 30 days</span></div><div class="table-wrap"><table>
            <thead><tr><th>Method</th><th class="num">Payments</th><th class="num">Amount</th></tr></thead>
            <tbody>@forelse ($byMethod as $r)<tr><td>{{ $r->method }}</td><td class="num">{{ $r->n }}</td><td class="num">{{ $m($r->total) }}</td></tr>@empty<tr><td colspan="3" class="empty">No payments.</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Receivables Aging</h2><a class="btn sm ghost" href="{{ route('caboose.aging') }}">Details</a></div><div class="table-wrap"><table>
            <thead><tr><th>Aging</th><th class="num">Accounts</th><th class="num">Balance</th></tr></thead>
            <tbody>@foreach ($aging as [$bucket, $n, $total])<tr><td>{{ $bucket }}</td><td class="num">{{ $n }}</td><td class="num">{{ $m($total) }}</td></tr>@endforeach</tbody>
        </table></div></div>
    </div>
@endsection
