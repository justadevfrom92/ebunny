@extends('backend.layouts.app')

@section('crumb', $queue[0])

@section('content')
    @include('backend.partials.page-head', ['title' => $queue[0], 'sub' => e($queue[1]), 'actions' => '<a class="btn ghost" href="'.route('corral.queues.index').'">All Queues</a>'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Account</th><th>Customer</th><th>Plan</th><th>Status</th><th>Item</th><th>Age</th><th></th></tr></thead>
        <tbody>@forelse ($items as $item)
            <tr>
                <td class="mono">@if ($item->customer)<a href="{{ route('corral.customers.show', $item->customer) }}">{{ $item->customer->account }}</a>@endif</td>
                <td>{{ $item->customer?->name }}</td><td>{{ $item->customer?->plan?->name }}</td>
                <td>@if ($item->customer)@include('backend.partials.pill', ['text' => $item->customer->status, 'tone' => $item->customer->statusTone()])@endif</td>
                <td class="wrap">{{ $item->summary }}</td>
                <td>{{ $item->created_at->diffForHumans(null, true) }}</td>
                <td><form method="post" action="{{ route('corral.queues.resolve', $item) }}" class="inline">@csrf<button class="btn sm cyan">Mark Fixed</button></form></td>
            </tr>
        @empty <tr><td colspan="7" class="empty">Queue is clear.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
