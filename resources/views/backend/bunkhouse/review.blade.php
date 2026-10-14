@extends('backend.layouts.app')

@section('crumb', $r->user->name.' · '.$r->kind)

@section('content')
    @php $done = $r->status === 'complete'; @endphp
    @include('backend.partials.page-head', ['title' => $r->user->name.' · '.$r->kind.' Review', 'sub' => e($r->period).' · due '.$r->due_on->format('n/j/Y').' · '.view('backend.partials.pill', ['text' => $r->overdue() ? 'Overdue' : $r->statusLabel(), 'tone' => $r->statusTone()])->render(),
        'actions' => '<a class="btn ghost" href="'.route('bunkhouse.people.show', $r->user).'">'.e($r->user->name).'</a><a class="btn ghost" href="'.route('bunkhouse.reviews').'">All Reviews</a>'])

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Person</h2></div><div class="panel-body"><dl class="kv">
            <dt>Title</dt><dd>{{ $r->user->title }}</dd><dt>{{ $r->user->company ? 'Company' : 'Department' }}</dt><dd>{{ $r->user->company ?? $r->user->department }}</dd>
            <dt>Reviewer</dt><dd>{{ $r->reviewer?->name ?? '—' }}</dd><dt>Started</dt><dd>{{ $r->user->hired_on?->format('n/j/Y') ?? '—' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>This Period</h2></div><div class="panel-body"><dl class="kv">
            <dt>Training</dt><dd>{{ $training->whereNotNull('completed_on')->count() }} of {{ $training->count() }} done</dd>
            <dt>Kudos (year)</dt><dd>{{ $kudos }}</dd>
            <dt>Past Reviews</dt><dd>{{ $past->count() ?: 'None, first review' }}</dd>
        </dl></div></div>
        @if ($past->isNotEmpty())<div class="panel"><div class="panel-head"><h2>Past Ratings</h2></div><div class="table-wrap"><table><tbody>@foreach ($past as $p)<tr class="click" data-href="{{ route('bunkhouse.reviews.show', $p) }}"><td><a href="{{ route('bunkhouse.reviews.show', $p) }}">{{ $p->period }}</a></td><td>{{ $p->kind }}</td><td>{{ $p->rating ? $p->rating.'/5' : '—' }}</td></tr>@endforeach</tbody></table></div></div>
        @else<div class="panel"><div class="panel-head"><h2>Past Ratings</h2></div><div class="panel-body muted">This is {{ explode(' ', $r->user->name)[0] }}'s first review.</div></div>@endif
    </div>

    <form method="post" action="{{ route('bunkhouse.reviews.update', $r) }}" class="panel">@csrf @method('put')
        <div class="panel-head"><h2>Review</h2>@if ($done)<span class="muted">completed {{ $r->completed_at?->format('n/j/Y') }}</span>@endif</div>
        <div class="panel-body stack">
            @if ($done)
                <dl class="kv"><dt>Rating</dt><dd><b>{{ $r->rating }}/5</b> · {{ \App\Models\ErmReview::RATINGS[$r->rating] ?? '' }}</dd>
                    <dt>Self Review</dt><dd style="white-space:pre-line">{{ $r->self_review ?? '—' }}</dd><dt>Strengths</dt><dd style="white-space:pre-line">{{ $r->strengths }}</dd>
                    <dt>Growth</dt><dd style="white-space:pre-line">{{ $r->growth ?? '—' }}</dd><dt>Goals</dt><dd style="white-space:pre-line">{{ $r->goals ?? '—' }}</dd></dl>
                <div><input type="hidden" name="reopen" value="1"><button class="btn ghost">Reopen</button></div>
            @else
                <div class="field"><label>Step</label><div class="chips small">@foreach (\App\Models\ErmReview::STATUSES as $s => [$label])<label><input type="radio" name="status" value="{{ $s }}" @checked(old('status', $r->status) === $s)><span>{{ $label }}</span></label>@endforeach</div></div>
                <div class="field"><label>Rating</label><div class="chips">@foreach (\App\Models\ErmReview::RATINGS as $n => $label)<label><input type="radio" name="rating" value="{{ $n }}" @checked((string) old('rating', $r->rating) === (string) $n)><span><b>{{ $n }}</b><small>{{ $label }}</small></span></label>@endforeach</div></div>
                <div class="field"><label for="self_review">Self review</label><textarea id="self_review" name="self_review" rows="3">{{ old('self_review', $r->self_review) }}</textarea></div>
                <div class="field"><label for="strengths">Strengths</label><textarea id="strengths" name="strengths" rows="3">{{ old('strengths', $r->strengths) }}</textarea></div>
                <div class="field"><label for="growth">Where to grow</label><textarea id="growth" name="growth" rows="3">{{ old('growth', $r->growth) }}</textarea></div>
                <div class="field"><label for="goals">Goals for next period</label><textarea id="goals" name="goals" rows="3">{{ old('goals', $r->goals) }}</textarea></div>
                <div><button class="btn cyan">Save Review</button> <span class="help">Pick Complete as the step to finish it.</span></div>
            @endif
        </div>
    </form>
@endsection
