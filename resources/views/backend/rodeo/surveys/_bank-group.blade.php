<div class="sv-cat-head" style="--dot:{{ $dot }}"><span class="sv-dot" style="width:14px;height:14px"></span><h2>{{ $title }}</h2><span class="muted">{{ $items->count() }} {{ Str::plural('question', $items->count()) }}@if ($desc) · {{ $desc }}@endif</span></div>
<div class="panel sv-q" style="--dot:{{ $dot }};margin-bottom:8px"><div class="table-wrap"><table class="table">
    <thead><tr><th>Question</th><th style="min-width:220px">Answers</th><th class="num">In Surveys</th><th></th></tr></thead>
    <tbody>@forelse ($items as $b)
        <tr><td><b>{{ $b->question }}</b><div class="sv-type" style="margin-top:3px">{{ \App\Models\SurveyQuestion::TYPES[$b->type] ?? $b->type }}</div>@if ($b->help)<div class="help">{{ $b->help }}</div>@endif</td>
            <td>@if ($b->answerSet)<div class="help" style="margin-bottom:3px">{{ $b->answerSet->name }}</div><div class="sv-opts">@foreach ($b->answerSet->options as $o)<span class="sv-opt">{{ $o }}</span>@endforeach</div>
                @elseif ($b->type === 'rating')<span class="help">0 to 10</span>@elseif ($b->type === 'yes_no')<span class="help">Yes · No</span>@else<span class="help">Written</span>@endif</td>
            <td class="num">{{ $b->uses_count }}</td>
            <td class="actions nowrap"><a class="btn sm ghost" href="{{ route('rodeo.surveys.bank.edit', $b) }}">Edit</a>
                <form method="post" action="{{ route('rodeo.surveys.bank.destroy', $b) }}" class="inline" data-confirm="Remove this question from the bank?|Surveys that use it keep their own copy.|Remove">@csrf @method('delete')<button class="btn sm ghost">Remove</button></form></td></tr>
    @empty <tr><td colspan="4" class="empty">No questions in this category yet.</td></tr> @endforelse</tbody>
</table></div></div>
