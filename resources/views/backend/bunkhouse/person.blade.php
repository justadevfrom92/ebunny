@extends('backend.layouts.app')

@section('crumb', $u->name)

{{-- One person: profile, team, reviews, one-on-ones, training, time off, kudos, HR cases and onboarding/offboarding --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $u->name, 'sub' => e($u->title ?? 'Employee').' · '.e($u->company ? $u->company.' (3rd party)' : ($u->department ?? 'No department')).($u->active ? '' : ' · <b>Former</b>'),
        'actions' => '<a class="btn ghost" href="'.route('bunkhouse.checkins.create', ['user' => $u->id]).'">Log Check-in</a><a class="btn ghost" href="'.route('bunkhouse.time-off.create', ['user' => $u->id]).'">Request Time Off</a><a class="btn cyan" href="'.route('bunkhouse.cases.create', ['user' => $u->id]).'">Open HR Case</a>'])

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Profile</h2></div><div class="panel-body"><dl class="kv">
            <dt>Title</dt><dd>{{ $u->title ?? '—' }}</dd>
            @if ($u->company)<dt>Company</dt><dd>{{ $u->company }} <span class="pill info">3rd party</span></dd><dt>Access Ends</dt><dd>{{ $u->access_ends_on?->format('n/j/Y') ?? 'No end date' }}</dd>
            @else<dt>Department</dt><dd>{{ $u->department ?? '—' }}</dd><dt>Employee #</dt><dd class="mono">{{ $u->employee_id ?? '—' }}</dd>@endif
            <dt>{{ $u->company ? 'Our Contact' : 'Manager' }}</dt><dd>@if ($u->manager)<a href="{{ route('bunkhouse.people.show', $u->manager) }}">{{ $u->manager->name }}</a>@else — @endif</dd>
            <dt>Started</dt><dd>{{ $u->hired_on?->format('n/j/Y') ?? '—' }}@if ($u->tenure()) <span class="muted">({{ $u->tenure() }})</span>@endif</dd>
            <dt>Location</dt><dd>{{ $u->location ?? '—' }}</dd>
            <dt>Email</dt><dd><a href="mailto:{{ $u->email }}">{{ $u->email }}</a>{{ $u->phone_ext ? ' · ext '.$u->phone_ext : '' }}</dd>
            <dt>Admin Role</dt><dd>{{ $u->role?->name ?? '—' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>At a Glance</h2></div><div class="panel-body"><dl class="kv">
            <dt>Last Review</dt><dd>@php $lr = $reviews->firstWhere('status', 'complete'); @endphp{{ $lr ? ($lr->rating ? $lr->rating.'/5 · '.\App\Models\ErmReview::RATINGS[$lr->rating] : 'Done').' ('.$lr->period.')' : 'None yet' }}</dd>
            <dt>Next Review</dt><dd>@php $nr = $reviews->where('status', '!=', 'complete')->sortBy('due_on')->first(); @endphp{{ $nr ? $nr->kind.' due '.$nr->due_on->format('n/j/Y') : 'Not scheduled' }}</dd>
            @php $own = $checkins->where('user_id', $u->id)->first(); @endphp<dt>Last Check-in</dt><dd>{{ $own ? $own->held_on->format('n/j/Y').' · '.$own->moodLabel() : ($u->manager_id ? 'None yet' : 'Holds them with the team') }}</dd>
            <dt>Training</dt><dd>{{ $training->whereNotNull('completed_on')->count() }} of {{ $training->count() }} done{{ $training->filter->overdue()->count() ? ', '.$training->filter->overdue()->count().' overdue' : '' }}</dd>
            <dt>PTO This Year</dt><dd>{{ rtrim(rtrim(number_format($ptoUsed, 1), '0'), '.') }} hours</dd>
            <dt>Kudos</dt><dd>{{ $kudos->count() }} received</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Team</h2><span class="muted">{{ $u->reports->count() }} direct {{ Str::plural('report', $u->reports->count()) }}</span></div>
            @if ($u->reports->isNotEmpty())<div class="table-wrap"><table><tbody>@foreach ($u->reports->sortBy('name') as $r)<tr class="click" data-href="{{ route('bunkhouse.people.show', $r) }}"><td>@include('backend.bunkhouse._who', ['p' => $r])</td></tr>@endforeach</tbody></table></div>
            @else<div class="panel-body muted">No one reports to {{ explode(' ', $u->name)[0] }}.</div>@endif
        </div>
    </div>

    @foreach ($checklist as $kind => $items)
        <div class="panel"><div class="panel-head"><h2>{{ ucfirst($kind) }}</h2><span class="muted">{{ $items->whereNotNull('done_at')->count() }} of {{ $items->count() }} done</span></div><div class="table-wrap"><table>
            <thead><tr><th>Step</th><th>Team</th><th>Due</th><th>Done</th><th></th></tr></thead>
            <tbody>@foreach ($items as $i)<tr><td @class(['muted' => $i->done_at])>{{ $i->task }}</td><td>{{ $i->team }}</td><td class="nowrap"><span @class(['bad-text' => ! $i->done_at && $i->due_on?->lt(today())])>{{ $i->due_on?->format('n/j/Y') }}</span></td><td>{{ $i->done_at ? $i->done_at->format('n/j').' · '.$i->doer?->name : '—' }}</td>
                <td class="actions"><form method="post" action="{{ route('bunkhouse.lifecycle.toggle', $i) }}" class="inline">@csrf<button class="btn sm {{ $i->done_at ? 'ghost' : '' }}">{{ $i->done_at ? 'Undo' : 'Done' }}</button></form></td></tr>@endforeach</tbody>
        </table></div></div>
    @endforeach

    <div class="panel"><div class="panel-head"><h2>Reviews</h2><a class="btn sm ghost" href="{{ route('bunkhouse.reviews.create', ['user' => $u->id]) }}">Schedule</a></div><div class="table-wrap"><table>
        <thead><tr><th>Review</th><th>Reviewer</th><th>Due</th><th>Rating</th><th>Status</th></tr></thead>
        <tbody>@forelse ($reviews as $r)<tr class="click" data-href="{{ route('bunkhouse.reviews.show', $r) }}"><td><a href="{{ route('bunkhouse.reviews.show', $r) }}">{{ $r->kind }} · {{ $r->period }}</a></td><td>{{ $r->reviewer?->name ?? '—' }}</td><td>{{ $r->due_on->format('n/j/Y') }}</td><td>{{ $r->rating ? $r->rating.'/5' : '—' }}</td><td>@include('backend.partials.pill', ['text' => $r->overdue() ? 'Overdue' : $r->statusLabel(), 'tone' => $r->statusTone()])</td></tr>
        @empty <tr><td colspan="5" class="empty">No reviews yet.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Check-ins</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($checkins as $c)<tr class="click" data-href="{{ route('bunkhouse.checkins.show', $c) }}"><td class="nowrap"><a href="{{ route('bunkhouse.checkins.show', $c) }}">{{ $c->held_on->format('n/j/Y') }}</a></td><td>{{ $c->user_id === $u->id ? 'with '.($c->manager?->name ?? '—') : 'held with '.$c->user->name }}</td><td>{{ $c->moodLabel() }}</td><td class="wrap">{{ Str::limit($c->topics, 80) }}</td></tr>
            @empty <tr><td class="empty">No check-ins yet.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Training</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($training as $e)@php [$label, $tone] = $e->stateLabel(); @endphp<tr class="click" data-href="{{ route('bunkhouse.training.show', $e->course) }}"><td><a href="{{ route('bunkhouse.training.show', $e->course) }}">{{ $e->course->name }}</a><div class="help">{{ $e->course->category }}</div></td><td>@include('backend.partials.pill', ['text' => $label, 'tone' => $tone])</td><td class="num">{{ $e->score !== null ? $e->score.'%' : '' }}</td></tr>
            @empty <tr><td class="empty">No training assigned.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Time Off</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($timeOff as $t)<tr><td class="nowrap">{{ $t->dates() }}</td><td>{{ $t->type }} · {{ rtrim(rtrim(number_format($t->hours, 1), '0'), '.') }}h</td><td>@include('backend.partials.pill', ['text' => ucfirst($t->status), 'tone' => $t->statusTone()])</td></tr>
            @empty <tr><td class="empty">No time off.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Kudos Received</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($kudos as $k)<tr><td><div class="wrap">“{{ $k->message }}”</div><div class="help">{{ $k->from?->name }} · {{ $k->value }} · {{ $k->created_at->format('n/j/Y') }}</div></td></tr>
            @empty <tr><td class="empty">No kudos yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>

    @if ($cases->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>HR Cases</h2><span class="muted">confidential</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($cases as $c)<tr class="click" data-href="{{ route('bunkhouse.cases.show', $c) }}"><td><a href="{{ route('bunkhouse.cases.show', $c) }}">{{ $c->subject }}</a><div class="help">{{ $c->type }}</div></td><td>{{ $c->created_at->format('n/j/Y') }}</td><td>@include('backend.partials.pill', ['text' => $c->statusLabel(), 'tone' => $c->statusTone()])</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
