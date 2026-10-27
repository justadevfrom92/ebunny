@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Payments', 'sub' => 'Every payment, with reversal (needs the refunds right). Record checks, money orders and cash received.',
        'actions' => '<a class="btn cyan" href="'.route('caboose.payments.create').'">Record a Payment</a>'])

            <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel"><div class="panel-head"><h2>Search Payments</h2></div><div class="panel-body form-row">
                <div class="field"><label for="start">From</label><input id="start" name="start" type="date" value="{{ $f['start'] }}"></div>
                <div class="field"><label for="end">To</label><input id="end" name="end" type="date" value="{{ $f['end'] }}"></div>
                <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>@foreach (['Success', 'Pending', 'Failed', 'Reversed'] as $s)<option @selected($f['status'] === $s)>{{ $s }}</option>@endforeach</select></div>
                <div class="field"><label for="account">Account</label><input id="account" name="account" value="{{ $f['account'] }}" style="width:13ch"></div>
                <button class="btn">Search</button>
            </div></form>
    </aside>
    <div class="search-main">

            <div class="panel"><div class="panel-head"><h2>Payments</h2><span class="muted">${{ number_format($total, 2) }} successful</span></div><div class="table-wrap"><table>
                <thead><tr><th>ID</th><th>Date</th><th>Account</th><th class="num">Amount</th><th>Method</th><th>Source</th><th>Kind</th><th>Status</th><th></th></tr></thead>
                <tbody>@forelse ($payments as $p)
                    @php $u = \App\Support\Records::url('payment', $p->id); @endphp<tr><td class="nowrap"><a href="{{ $u }}"><b>#{{ $p->id }}</b></a></td><td>{{ $p->paid_on?->format('n/j/Y') }}</td><td>@include('backend.partials.account-link', ['c' => $p->customer])<br><span class="help">{{ $p->customer?->name }}</span></td>
                        <td class="num">${{ number_format($p->amount, 2) }}</td><td>{{ $p->method }}</td><td>{{ $p->source }}</td><td>{{ $p->kind }}</td>
                        <td>@include('backend.partials.pill', ['text' => $p->status, 'tone' => ['Success' => 'ok', 'Pending' => 'info', 'Reversed' => 'warn'][$p->status] ?? 'bad'])</td>
                        <td>@if ($p->status === 'Success')@can('refunds')<form method="post" action="{{ route('caboose.payments.reverse', $p) }}" class="inline" data-confirm="Reverse this payment?|${{ number_format($p->amount, 2) }} goes back on account {{ $p->customer?->account }}.|Reverse">@csrf<button class="btn sm ghost">Reverse</button></form>@endcan @endif</td></tr>
                @empty <tr><td colspan="9" class="empty">No payments match.</td></tr> @endforelse</tbody>
            </table></div>@include('backend.partials.pager', ['p' => $payments])</div>
    </div>
    </div>
@endsection
