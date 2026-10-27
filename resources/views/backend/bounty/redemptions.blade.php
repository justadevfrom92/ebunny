@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Redemptions', 'sub' => 'Offers customers redeemed. Gift cards are sent by the gift card vendor; mark each one sent here.'])
    <div class="panel">
        <div class="panel-head"><nav class="tabs"><a href="{{ route('bounty.redemptions') }}" @class(['on' => $pendingOnly])>Gift Cards to Send</a><a href="{{ route('bounty.redemptions', ['show' => 'all']) }}" @class(['on' => ! $pendingOnly])>All Redemptions</a></nav></div>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Date</th><th>Account</th><th>Offer</th><th class="num">Stars</th><th>Gift Card</th><th></th></tr></thead>
            <tbody>@forelse ($rows as $s)
                @php $u = \App\Support\Records::url('star_entry', $s->id); @endphp<tr><td class="nowrap"><a href="{{ $u }}"><b>#{{ $s->id }}</b></a></td><td>{{ $s->created_at->format('n/j/Y') }}</td><td>@include('backend.partials.account-link', ['c' => $s->customer])<br><span class="help">{{ $s->customer?->name }} · {{ $s->customer?->email }}</span></td>
                    <td>{{ str_replace('Redeemed: ', '', $s->reason) }}</td><td class="num">{{ number_format(abs($s->stars)) }}</td>
                    <td>@if ($s->fulfillment)@include('backend.partials.pill', ['text' => $s->fulfillment, 'tone' => $s->fulfillment === 'sent' ? 'ok' : 'warn'])@else — @endif</td>
                    <td>@if ($s->fulfillment === 'pending')<form method="post" action="{{ route('bounty.fulfil', $s) }}" class="inline">@csrf<button class="btn sm">Mark Sent</button></form>@endif</td></tr>
            @empty <tr><td colspan="7" class="empty">{{ $pendingOnly ? 'No gift cards waiting.' : 'No redemptions yet.' }}</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $rows])
    </div>
@endsection
