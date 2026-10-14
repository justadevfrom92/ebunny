@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Time Off', 'sub' => 'Requests waiting for a decision, who\'s out over the next two weeks, and recent decisions.',
        'actions' => '<a class="btn cyan" href="'.route('bunkhouse.time-off.create').'">Request Time Off</a>'])

    <div class="panel"><div class="panel-head"><h2>Waiting for a Decision</h2><span class="muted">{{ $pending->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Person</th><th>Dates</th><th>Type</th><th class="num">Hours</th><th>Note</th><th></th></tr></thead>
        <tbody>@forelse ($pending as $t)<tr><td>@include('backend.bunkhouse._who', ['p' => $t->user])</td><td class="nowrap">{{ $t->dates() }}</td><td>{{ $t->type }}</td><td class="num">{{ rtrim(rtrim(number_format($t->hours, 1), '0'), '.') }}</td><td class="wrap">{{ $t->note ?? '—' }}</td>
            <td class="actions">@foreach (['approved' => 'Approve', 'denied' => 'Deny'] as $do => $l)<form method="post" action="{{ route('bunkhouse.time-off.decide', $t) }}" class="inline">@csrf<input type="hidden" name="do" value="{{ $do }}"><button class="btn sm {{ $do === 'approved' ? '' : 'ghost' }}">{{ $l }}</button></form>@endforeach</td></tr>
        @empty <tr><td colspan="6" class="empty">Nothing waiting.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>Who's Out</h2><span class="muted">next 2 weeks</span></div><div class="table-wrap"><table class="out-grid">
        <thead><tr><th>Person</th>@foreach ($days as $d)<th @class(['wkend' => $d->isWeekend()]) title="{{ $d->format('l n/j') }}">{{ $d->format('D') }}<br>{{ $d->format('n/j') }}</th>@endforeach</tr></thead>
        <tbody>@forelse ($out->groupBy('user_id') as $list)<tr><td class="nowrap">@include('backend.bunkhouse._who', ['p' => $list->first()->user, 'short' => true])</td>
            @foreach ($days as $d)@php $t = $list->first(fn ($t) => $d->between($t->starts_on, $t->ends_on)); @endphp<td @class(['wkend' => $d->isWeekend(), 'off' => $t && ! $d->isWeekend()]) @if ($t) title="{{ $t->type }}" @endif>{{ $t && ! $d->isWeekend() ? substr($t->type, 0, 3) : '' }}</td>@endforeach</tr>
        @empty <tr><td colspan="{{ $days->count() + 1 }}" class="empty">Nobody's out in the next two weeks.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>Recent Decisions</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Person</th><th>Dates</th><th>Type</th><th class="num">Hours</th><th>Decision</th><th>By</th></tr></thead>
        <tbody>@forelse ($decided as $t)<tr><td>@include('backend.bunkhouse._who', ['p' => $t->user, 'short' => true])</td><td class="nowrap">{{ $t->dates() }}</td><td>{{ $t->type }}</td><td class="num">{{ rtrim(rtrim(number_format($t->hours, 1), '0'), '.') }}</td>
            <td>@include('backend.partials.pill', ['text' => ucfirst($t->status), 'tone' => $t->statusTone()])</td><td>{{ $t->approver?->name }}<div class="help">{{ $t->decided_at?->format('n/j/Y') }}</div></td></tr>
        @empty <tr><td colspan="6" class="empty">No decisions yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
