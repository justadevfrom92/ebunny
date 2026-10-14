@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Kudos', 'sub' => 'Thank-yous between coworkers, each tied to one of our values.'])

    <form method="post" action="{{ route('bunkhouse.kudos.store') }}" class="panel">@csrf
        <div class="panel-head"><h2>Give Kudos</h2></div>
        <div class="panel-body stack">
            <div class="field"><label>To</label>@include('backend.bunkhouse._pick', ['name' => 'to_id', 'people' => $people->reject(fn ($p) => $p->is(auth()->user()))])</div>
            <div class="field"><label>For</label><div class="chips small">@foreach (\App\Models\ErmKudo::VALUES as $v)<label><input type="radio" name="value" value="{{ $v }}" @checked(old('value', 'Teamwork') === $v)><span>{{ $v }}</span></label>@endforeach</div></div>
            <div class="field"><label for="message">Message</label><textarea id="message" name="message" rows="2" maxlength="500" required>{{ old('message') }}</textarea></div>
            <div><button class="btn cyan">Send Kudos</button></div>
        </div>
    </form>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Most Thanked</h2><span class="muted">90 days</span></div><div class="table-wrap"><table>
            <tbody>@forelse ($top as [$u, $n])<tr class="click" data-href="{{ route('bunkhouse.people.show', $u) }}"><td>@include('backend.bunkhouse._who', ['p' => $u])</td><td class="num">{{ $n }}</td></tr>
            @empty <tr><td class="empty">No kudos yet.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>By Value</h2><span class="muted">90 days</span></div><div class="panel-body">
            <x-bar-chart :bars="collect(\App\Models\ErmKudo::VALUES)->map(fn ($v) => ['label' => $v, 'value' => $byValue[$v] ?? 0])" unit="kudos" /></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>All Kudos</h2></div><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>To</th><th>From</th><th>Value</th><th>Message</th></tr></thead>
        <tbody>@forelse ($kudos as $k)<tr><td class="nowrap">{{ $k->created_at->format('n/j/Y') }}</td><td>@include('backend.bunkhouse._who', ['p' => $k->to, 'short' => true])</td><td>{{ $k->from?->name }}</td><td>{{ $k->value }}</td><td class="wrap">{{ $k->message }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No kudos yet.</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $kudos])</div>
@endsection
