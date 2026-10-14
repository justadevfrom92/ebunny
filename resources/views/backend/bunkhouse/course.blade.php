@extends('backend.layouts.app')

@section('crumb', $c->name)

@section('content')
    @php $done = $c->enrollments->whereNotNull('completed_on'); @endphp
    @include('backend.partials.page-head', ['title' => $c->name, 'sub' => e($c->category).' · '.rtrim(rtrim(number_format($c->hours, 2), '0'), '.').' hours · required for '.e($c->requiredLabel()).($c->renew_months ? ' · retake every '.$c->renew_months.' months' : ''),
        'actions' => '<a class="btn ghost" href="'.route('bunkhouse.training').'">All Courses</a>'])
    @if ($c->description)<p class="muted" style="margin-top:-6px">{{ $c->description }}</p>@endif

    <div class="grid-4 stat-row">
        <div class="stat"><span>Assigned</span><strong>{{ $c->enrollments->count() }}</strong></div>
        <div class="stat"><span>Done</span><strong>{{ $done->count() }}</strong><small>{{ $c->enrollments->count() ? round(100 * $done->count() / $c->enrollments->count()) : 0 }}%</small></div>
        <div class="stat"><span>Overdue</span><strong>{{ $c->enrollments->filter->overdue()->count() }}</strong></div>
        <div class="stat"><span>Average Score</span><strong>{{ $done->whereNotNull('score')->count() ? round($done->whereNotNull('score')->avg('score')).'%' : '—' }}</strong></div>
    </div>

    @if ($missing->isNotEmpty())<div class="banner-note" role="status" style="margin-bottom:16px">{{ $missing->count() }} {{ Str::plural('person', $missing->count()) }} who must take this {{ $missing->count() === 1 ? 'hasn\'t' : 'haven\'t' }} been assigned it: {{ $missing->take(6)->pluck('name')->implode(', ') }}{{ $missing->count() > 6 ? '…' : '' }}</div>@endif

    <div class="panel"><div class="panel-head"><h2>People</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Person</th><th>Assigned</th><th>Status</th><th class="num">Score</th><th></th></tr></thead>
        <tbody>@foreach ($c->enrollments as $e)@php [$label, $tone] = $e->stateLabel(); @endphp
            <tr><td>@include('backend.bunkhouse._who', ['p' => $e->user])</td><td class="nowrap">{{ $e->assigned_on->format('n/j/Y') }}</td><td>@include('backend.partials.pill', ['text' => $label, 'tone' => $tone])</td><td class="num">{{ $e->score !== null ? $e->score.'%' : '—' }}</td>
                <td class="actions"><form method="post" action="{{ route('bunkhouse.training.complete', $e) }}" class="inline">@csrf<button class="btn sm {{ $e->completed_on ? 'ghost' : '' }}">{{ $e->completed_on ? 'Undo' : 'Mark Done' }}</button></form></td></tr>@endforeach</tbody>
    </table></div></div>

    <form method="post" action="{{ route('bunkhouse.training.assign', $c) }}" class="panel">@csrf
        <div class="panel-head"><h2>Assign</h2></div>
        <div class="panel-body stack">
            <div class="field"><label for="due_on">Due</label><input id="due_on" name="due_on" type="date" required value="{{ today()->addDays(30)->toDateString() }}" style="max-width:20ch"></div>
            <div class="field"><label>People</label>@include('backend.bunkhouse._pick', ['name' => 'user_ids[]', 'people' => $people, 'multiple' => true, 'selected' => $missing->pluck('id')->all()])<div class="help">People who must take it and don't have it yet are already ticked.</div></div>
            <div><button class="btn cyan">Assign</button></div>
        </div>
    </form>
@endsection
