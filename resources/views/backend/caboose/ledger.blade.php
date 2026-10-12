@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Credits & Debits', 'sub' => 'Bill credits, promos and manual debits added in Corral or by rewards wait here until finance applies them to the balance.'])
    <div class="panel"><div class="panel-head"><h2>Pending</h2><span class="muted">{{ $pending->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Added</th><th>Account</th><th>Kind</th><th class="num">Amount</th><th>Description</th><th>By</th><th></th></tr></thead>
        <tbody>@forelse ($pending as $e)
            <tr><td>{{ $e->created_at->format('n/j/Y') }}</td><td>{{ $e->customer?->account }}<br><span class="help">{{ $e->customer?->name }}</span></td><td>{{ ucfirst($e->kind) }}</td>
                <td class="num">${{ number_format($e->amount, 2) }}</td><td>{{ $e->description }}</td><td>{{ $e->user?->name ?? 'System' }}</td>
                <td class="actions">@foreach (['apply' => 'Apply', 'reject' => 'Reject'] as $do => $l)<form method="post" action="{{ route('caboose.ledger.decide', $e) }}" class="inline">@csrf<input type="hidden" name="do" value="{{ $do }}"><button class="btn sm {{ $do === 'apply' ? '' : 'ghost' }}">{{ $l }}</button></form>@endforeach</td></tr>
        @empty <tr><td colspan="7" class="empty">Nothing pending.</td></tr> @endforelse</tbody>
    </table></div></div>
    <div class="panel"><div class="panel-head"><h2>Recently Decided</h2></div><div class="table-wrap"><table>
        <tbody>@forelse ($recent as $e)<tr><td>{{ $e->updated_at->format('n/j/Y') }}</td><td>{{ $e->customer?->account }}</td><td>{{ ucfirst($e->kind) }}</td><td class="num">${{ number_format($e->amount, 2) }}</td><td>{{ $e->description }}</td><td>@include('backend.partials.pill', ['text' => $e->status, 'tone' => $e->status === 'applied' ? 'ok' : ''])</td></tr>
        @empty <tr><td class="empty">None yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
