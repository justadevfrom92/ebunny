@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Training', 'sub' => 'Courses, who has to take them, and how far along everyone is. Open a course to assign it or mark people complete.'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Course</th><th>Category</th><th>Required For</th><th class="num">Hours</th><th class="num">Assigned</th><th class="num">Done</th><th class="num">Overdue</th><th>Complete</th></tr></thead>
        <tbody>@foreach ($courses as $c)@php $pct = $c->enrollments_count ? round(100 * $c->done_count / $c->enrollments_count) : 0; @endphp
            <tr class="click" data-href="{{ route('bunkhouse.training.show', $c) }}"><td><a href="{{ route('bunkhouse.training.show', $c) }}"><b>{{ $c->name }}</b></a>@if ($c->renew_months)<div class="help">every {{ $c->renew_months }} months</div>@endif</td><td>{{ $c->category }}</td><td>{{ $c->requiredLabel() }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($c->hours, 2), '0'), '.') }}</td><td class="num">{{ $c->enrollments_count }}</td><td class="num">{{ $c->done_count }}</td><td class="num">@if ($c->overdue_count)<span class="bad-text">{{ $c->overdue_count }}</span>@else 0 @endif</td>
                <td style="min-width:120px"><div class="sv-bar" style="grid-template-columns:1fr 44px"><span class="track"><span class="fill" style="width:{{ $pct }}%"></span></span><span class="n">{{ $pct }}%</span></div></td></tr>@endforeach</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>Overdue</h2><span class="muted">{{ $overdue->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Person</th><th>Course</th><th>Was Due</th><th></th></tr></thead>
        <tbody>@forelse ($overdue as $e)<tr class="click" data-href="{{ route('bunkhouse.training.show', $e->course) }}"><td>@include('backend.bunkhouse._who', ['p' => $e->user])</td><td>{{ $e->course->name }}</td><td class="nowrap"><span class="bad-text">{{ $e->due_on->format('n/j/Y') }}</span><div class="help">{{ $e->due_on->diffForHumans() }}</div></td>
            <td class="actions"><form method="post" action="{{ route('bunkhouse.training.complete', $e) }}" class="inline">@csrf<button class="btn sm">Mark Done</button></form></td></tr>
        @empty <tr><td colspan="4" class="empty">Nothing overdue.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
