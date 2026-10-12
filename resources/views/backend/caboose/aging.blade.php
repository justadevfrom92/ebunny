@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Receivables Aging', 'sub' => 'Open balances by days past due.'])
    <form method="get" class="panel"><div class="panel-body form-row"><div class="field"><label for="as_of">As Of</label><input id="as_of" name="as_of" type="date" value="{{ $f['as_of'] }}"></div><button class="btn">Update</button></div></form>
    <div class="grid-4 stat-row" style="grid-template-columns:repeat(5,1fr)">
        @foreach ($summary as [$b, $n, $total])<div class="stat"><span>{{ $b }}</span><strong>${{ number_format($total, 0) }}</strong><small>{{ $n }} accounts</small></div>@endforeach
    </div>
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Account</th><th>Customer</th><th>Status</th><th>Market</th><th class="num">Balance</th><th>Due</th><th class="num">Days Past Due</th><th>Aging</th></tr></thead>
        <tbody>@forelse ($accounts as $c)
            <tr><td>{{ $c->account }}</td><td>{{ $c->name }}</td><td>{{ $c->status }}</td><td>{{ $c->market?->name }}</td><td class="num">${{ number_format($c->balance, 2) }}</td>
                <td>{{ $c->due_date?->format('n/j/Y') ?? '—' }}</td><td class="num">{{ $c->days_past_due }}</td><td>@include('backend.partials.pill', ['text' => $bucket($c->days_past_due), 'tone' => $c->days_past_due > 60 ? 'bad' : ($c->days_past_due > 0 ? 'warn' : 'ok')])</td></tr>
        @empty <tr><td colspan="8" class="empty">No open balances.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
