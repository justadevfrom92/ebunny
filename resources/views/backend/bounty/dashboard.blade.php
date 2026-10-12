@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Rewards', 'sub' => 'The rewards program: stars earned and spent, offers and gift cards.'])
    <div class="grid-4 stat-row">
        <div class="stat"><span>Members With Stars</span><strong>{{ number_format($members) }}</strong></div>
        <div class="stat"><span>Stars Outstanding</span><strong>{{ number_format($outstanding) }}</strong></div>
        <div class="stat"><span>Earned This Month</span><strong>{{ number_format($earned) }}</strong></div>
        <div class="stat"><span>Redeemed This Month</span><strong>{{ number_format($redeemed) }}</strong></div>
    </div>
    @if ($giftsPending)<div class="alert info"><b>{{ $giftsPending }} gift card{{ $giftsPending > 1 ? 's' : '' }}</b> waiting to be sent. <a href="{{ route('bounty.redemptions') }}">Redemptions</a></div>@endif
    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Most Redeemed Offers</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($popular as [$name, $n])<tr><td>{{ $name }}</td><td class="num">{{ $n }}</td></tr>@empty<tr><td class="empty">No redemptions yet.</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Top Members</h2></div><div class="table-wrap"><table>
            <tbody>@foreach ($top as $c)<tr class="click" data-href="{{ route('bounty.members', ['account' => $c->account]) }}"><td><a href="{{ route('bounty.members', ['account' => $c->account]) }}">{{ $c->account }}</a></td><td>{{ $c->name }}</td><td class="num">{{ number_format($c->stars) }} ★</td></tr>@endforeach</tbody>
        </table></div></div>
    </div>
@endsection
