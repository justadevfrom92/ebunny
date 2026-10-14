@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Reviews', 'sub' => 'Annual, mid-year and 90-day reviews, and check-ins for 3rd-party users. Each goes Scheduled → Self Review → Manager Review → Complete.',
        'actions' => '<a class="btn cyan" href="'.route('bunkhouse.reviews.create').'">Schedule Reviews</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Overdue</span><strong>{{ $overdue->count() }}</strong></div>
        <div class="stat"><span>In Progress</span><strong>{{ $open->count() }}</strong><small>{{ $open->where('due_on', '<=', today()->addDays(14))->count() }} due in 2 weeks</small></div>
        <div class="stat"><span>Completed</span><strong>{{ $ratings->sum() }}</strong></div>
        <div class="stat"><span>Average Rating</span><strong>{{ $ratings->sum() ? number_format($ratings->map(fn ($n, $r) => $n * $r)->sum() / $ratings->sum(), 1) : '—' }}</strong><small>out of 5</small></div>
    </div>

    @foreach (['Overdue' => $overdue, 'Open' => $open] as $title => $list)
        <div class="panel"><div class="panel-head"><h2>{{ $title }}</h2><span class="muted">{{ $list->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Person</th><th>Review</th><th>Reviewer</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>@forelse ($list as $r)<tr class="click" data-href="{{ route('bunkhouse.reviews.show', $r) }}"><td><a href="{{ route('bunkhouse.reviews.show', $r) }}"><b>{{ $r->user->name }}</b></a><div class="help">{{ $r->user->company ?? $r->user->department }}</div></td>
                <td>{{ $r->kind }} · {{ $r->period }}</td><td>{{ $r->reviewer?->name ?? '—' }}</td><td class="nowrap"><span @class(['bad-text' => $r->overdue()])>{{ $r->due_on->format('n/j/Y') }}</span></td><td>@include('backend.partials.pill', ['text' => $r->statusLabel(), 'tone' => $r->statusTone()])</td></tr>
            @empty <tr><td colspan="5" class="empty">{{ $title === 'Overdue' ? 'Nothing overdue.' : 'No open reviews.' }}</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Ratings</h2><span class="muted">completed reviews</span></div><div class="panel-body">
            <x-bar-chart :bars="collect(\App\Models\ErmReview::RATINGS)->map(fn ($l, $r) => ['label' => $r, 'value' => $ratings[$r] ?? 0, 'tip' => $r.' · '.$l])->values()" unit="reviews" /></div></div>
        <div class="panel"><div class="panel-head"><h2>Recently Completed</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($done as $r)<tr class="click" data-href="{{ route('bunkhouse.reviews.show', $r) }}"><td><a href="{{ route('bunkhouse.reviews.show', $r) }}">{{ $r->user->name }}</a><div class="help">{{ $r->kind }} · {{ $r->period }}</div></td><td>{{ $r->rating ? $r->rating.'/5' : '—' }}</td><td class="nowrap">{{ $r->completed_at?->format('n/j/Y') }}</td></tr>
            @empty <tr><td class="empty">None yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
