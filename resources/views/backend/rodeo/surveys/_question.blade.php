{{-- One question in the survey builder: picked from the Question Bank. $i is its form index, $q its values. --}}
<div class="sv-qcard">
    <div class="sv-qhead">
        <span class="num">Q</span><span class="sv-dot"></span>
        <select class="sv-pick" data-f="pick" aria-label="Question" required>
            <option value="">Choose a question…</option>
            @if (! empty($q['question']) && empty($q['bank']))<option value="own" selected>{{ Str::limit($q['question'], 90) }} (this survey's own)</option>@endif
            @foreach ($bank->groupBy(fn ($b) => $b->category?->name ?? 'Uncategorized') as $cat => $items)<optgroup label="{{ $cat }}">@foreach ($items as $b)<option value="{{ $b->id }}" @selected(($q['bank'] ?? null) == $b->id)>{{ Str::limit($b->question, 90) }}</option>@endforeach</optgroup>@endforeach
        </select>
        <label class="check sv-req"><input type="hidden" name="q[{{ $i }}][required]" value="0"><input type="checkbox" name="q[{{ $i }}][required]" value="1" @checked(! empty($q['required'])) data-f="required"> Required</label>
        <button type="button" class="btn sm ghost" data-move="up" aria-label="Move up">↑</button>
        <button type="button" class="btn sm ghost" data-move="down" aria-label="Move down">↓</button>
        <button type="button" class="btn sm ghost" data-move="remove">Remove</button>
    </div>
    <div class="sv-qinfo" data-show="info"></div>
    @foreach (['id', 'bank', 'question', 'category', 'type', 'answer_set', 'options', 'help'] as $f)
        <input type="hidden" name="q[{{ $i }}][{{ $f }}]" value="{{ $q[$f] ?? '' }}" data-f="{{ $f }}">
    @endforeach
</div>
