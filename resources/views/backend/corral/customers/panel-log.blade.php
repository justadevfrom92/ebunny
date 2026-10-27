@extends('backend.layouts.app')

@section('crumb', $title)

{{-- Account page → Balances / Actions / Flags → Log: every entry, newest first, 25 to a page. --}}
@section('content')
    @php $money = fn ($v) => $v < 0 ? '($'.number_format(abs($v), 2).')' : '$'.number_format($v, 2); @endphp
    @include('backend.partials.page-head', ['title' => $title, 'sub' => e($c->name).' · Account <a class="mono" href="'.route('corral.customers.show', $c).'">'.e($c->account).'</a>',
        'actions' => '<a class="btn ghost btn-back" href="'.route('corral.customers.show', $c).'#'.($panel === 'balances' ? 'balances' : ($panel === 'flags' ? 'flags' : 'actions')).'">Back to Account</a>'])

    <div class="chips small" style="margin-bottom:14px">@foreach (\App\Http\Controllers\Backend\Corral\PanelLogController::PANELS as $k => $l)<a class="chip-link {{ $k === $panel ? 'on' : '' }}" href="{{ route('corral.customers.panel-log', [$c, $k]) }}">{{ $l }}</a>@endforeach</div>

    <div class="panel"><div class="panel-head"><h2>{{ number_format($rows->total()) }} {{ Str::plural('entry', $rows->total()) }}</h2></div>
        <div class="table-wrap"><table>
            @if ($panel === 'balances')
                <thead><tr><th>ID</th><th>When</th><th>Type</th><th class="num">Amount</th><th>Description</th><th>Status</th><th>By</th></tr></thead>
                <tbody>@forelse ($rows as $r)
                    @php $u = \App\Support\Records::url($r['record'], $r['id']); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $r['id'] }}</b></a></td><td class="nowrap">{{ $r['at']?->format('n/j/Y g:i A') }}</td><td>{{ $r['type'] }}</td><td class="num">{{ $money($r['amount']) }}</td><td class="wrap">{{ $r['description'] }}</td>
                        <td>@include('backend.partials.pill', ['text' => $r['status'], 'tone' => ['Applied' => 'ok', 'Success' => 'ok', 'Pending' => 'warn', 'Rejected' => 'bad', 'Failed' => 'bad', 'Reversed' => 'warn'][$r['status']] ?? ''])</td><td>{{ $r['by'] ?? '—' }}</td></tr>
                @empty <tr><td colspan="7" class="empty">No credits, debits or payments yet.</td></tr> @endforelse</tbody>
            @elseif ($panel === 'actions')
                <thead><tr><th>ID</th><th>When</th><th>Action</th><th>Result</th><th>By</th></tr></thead>
                <tbody>@forelse ($rows as $n)
                    @php $u = route('corral.items.show', ['id' => $n->id, 'model' => 'ItemNote_model']); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $n->id }}</b></a></td><td class="nowrap">{{ $n->created_at->format('n/j/Y g:i A') }}</td><td><b>{{ $n->action }}</b></td><td class="wrap">{{ \Illuminate\Support\Str::after($n->body, $n->action.': ') }}</td><td>{{ $n->author ?? '—' }}</td></tr>
                @empty <tr><td colspan="5" class="empty">No actions run on this account yet.</td></tr> @endforelse</tbody>
            @else
                <thead><tr><th>ID</th><th>Flag</th><th>Added</th><th>By</th><th>Removed</th><th>Status</th></tr></thead>
                <tbody>@forelse ($rows as $f)
                    @php $u = \App\Support\Records::url('customer_flag', $f->id); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $f->id }}</b></a></td><td><b>{{ $f->flag }}</b></td><td class="nowrap">{{ $f->created_at->format('n/j/Y g:i A') }}</td><td>{{ $f->author ?? 'System' }}</td>
                        <td class="nowrap">{{ $f->removed_at?->format('n/j/Y g:i A') ?? '—' }}</td><td>@include('backend.partials.pill', ['text' => $f->removed_at ? 'Removed' : 'On the account', 'tone' => $f->removed_at ? '' : 'warn'])</td></tr>
                @empty <tr><td colspan="6" class="empty">No flags yet.</td></tr> @endforelse</tbody>
            @endif
        </table></div>
        @include('backend.partials.pager', ['p' => $rows])
    </div>
@endsection
