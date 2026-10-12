@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Deposits', 'sub' => 'Deposits due from customers and deposits held. Record a deposit in Payments (For: Deposit); refund one in Refunds.'])
    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Due</h2><span class="muted">${{ number_format($due->sum('deposit_due'), 2) }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Account</th><th>Customer</th><th>Status</th><th class="num">Due</th></tr></thead>
            <tbody>@forelse ($due as $c)<tr><td>{{ $c->account }}</td><td>{{ $c->name }}</td><td>{{ $c->status }}</td><td class="num">${{ number_format($c->deposit_due, 2) }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No deposits due.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Held</h2><span class="muted">${{ number_format($held->sum('deposit_held'), 2) }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Account</th><th>Customer</th><th>Status</th><th class="num">Held</th></tr></thead>
            <tbody>@forelse ($held as $c)<tr><td>{{ $c->account }}</td><td>{{ $c->name }}</td><td>{{ $c->status }}</td><td class="num">${{ number_format($c->deposit_held, 2) }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No deposits held.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
