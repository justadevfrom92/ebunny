@extends('backend.layouts.app')

@section('crumb', $survey->exists ? $survey->title : 'New Survey')

@section('content')
    @php
        $rows = old('q') ? array_values(old('q')) : $questions->map(fn ($q) => ['id' => $q->id, 'bank' => $q->survey_bank_question_id, 'category' => $q->survey_category_id, 'question' => $q->question,
            'type' => $q->type, 'answer_set' => $q->survey_answer_set_id, 'options' => $q->survey_answer_set_id ? '' : implode("\n", $q->options ?? []), 'required' => $q->required, 'help' => $q->help])->all();
        $colors = $categories->pluck('color', 'id');
        $actions = $survey->exists
            ? '<a class="btn ghost" href="'.route('rodeo.surveys.results', $survey).'">Results</a><a class="btn ghost" href="'.url('survey/'.$survey->slug).'" target="_blank" rel="noopener">Open Survey</a>'
              .'<form method="post" action="'.route('rodeo.surveys.copy', $survey).'" class="inline">'.csrf_field().'<button class="btn ghost">Copy</button></form>'
            : null;
    @endphp
    @include('backend.partials.page-head', ['title' => $survey->exists ? $survey->title : 'New Survey',
        'sub' => $survey->exists ? 'Customers answer at <span class="mono">'.e(url('survey/'.$survey->slug)).'</span> · '.number_format($responses).' '.Str::plural('response', $responses) : 'Pick each question from the Question Bank; its category and answers come with it.',
        'actions' => $actions])

    <form method="post" action="{{ $survey->exists ? route('rodeo.surveys.update', $survey) : route('rodeo.surveys.store') }}" id="sv-form">@csrf @if ($survey->exists) @method('put') @endif
    <div class="sv-builder">
        <div style="display:grid;grid-template-columns:minmax(0, 1fr);gap:20px;min-width:0">
            <div class="panel">
                <div class="panel-head"><h2>Details</h2>@if ($survey->exists)@include('backend.partials.pill', ['text' => $survey->stateLabel(), 'tone' => $survey->isOpen() ? 'ok' : ''])@endif</div>
                <div class="panel-body form-grid">
                    <label for="title">Title</label><input id="title" name="title" required value="{{ old('title', $survey->title) }}">
                    <label for="intro">Intro</label><textarea id="intro" name="intro" maxlength="1000" style="min-height:60px;font-family:inherit" placeholder="Shown at the top of the survey">{{ old('intro', $survey->intro) }}</textarea>
                    <label for="thank_you">Thank-you message</label><textarea id="thank_you" name="thank_you" maxlength="1000" style="min-height:60px;font-family:inherit" placeholder="Shown after someone submits">{{ old('thank_you', $survey->thank_you) }}</textarea>
                    <label for="status">Status</label><select id="status" name="status">@foreach (['active' => 'Active (taking responses between the dates below)', 'closed' => 'Closed'] as $v => $l)<option value="{{ $v }}" @selected(old('status', $survey->status) === $v)>{{ $l }}</option>@endforeach</select>
                    <label for="opens_on">Opens</label><input id="opens_on" name="opens_on" type="date" value="{{ old('opens_on', $survey->opens_on?->toDateString()) }}" style="max-width:20ch">
                    <label for="closes_on">Closes</label><input id="closes_on" name="closes_on" type="date" value="{{ old('closes_on', $survey->closes_on?->toDateString()) }}" style="max-width:20ch">
                    <span></span><p class="help" style="margin:0">Leave the dates empty to keep it open until you close it.</p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Questions</h2><span class="muted" id="sv-count"></span></div>
                <div class="panel-body">
                    @if ($responses)<div class="banner-note" style="margin-bottom:12px">This survey has {{ $responses }} {{ Str::plural('response', $responses) }}. Rewording and reordering is safe; changing a question's type or answers changes how its past answers read in results.</div>@endif
                    <div id="sv-rows">@foreach ($rows as $i => $q)@include('backend.rodeo.surveys._question', ['i' => $i, 'q' => $q])@endforeach</div>
                    <p class="empty" id="sv-empty" @if ($rows) hidden @endif>No questions yet. Add one and pick it from the Question Bank.</p>
                    <div class="actions" style="margin-top:12px"><button type="button" class="btn ghost" id="sv-add">+ Add a Question</button><a class="help" href="{{ route('rodeo.surveys.bank.create') }}">Need a question that isn't there? Add it to the Question Bank</a></div>
                </div>
            </div>
        </div>

        <aside class="sv-side" style="display:grid;grid-template-columns:minmax(0, 1fr);gap:16px">
            <div class="panel"><div class="panel-body" style="display:grid;gap:10px">
                <button class="btn cyan">Save Survey</button>
                @if ($survey->exists && ! $responses)<button type="submit" form="sv-delete" class="btn ghost red-text">Delete Survey</button>@endif
            </div></div>
            <div class="panel">
                <div class="panel-head"><h3 style="margin:0">Outline</h3></div>
                <div class="panel-body"><ul class="sv-outline" id="sv-outline"></ul></div>
            </div>
        </aside>
    </div>
    </form>
    @if ($survey->exists && ! $responses)<form method="post" action="{{ route('rodeo.surveys.destroy', $survey) }}" id="sv-delete" data-confirm="Delete this survey?|It has no responses, so nothing else is lost.|Delete">@csrf @method('delete')</form>@endif

    <template id="sv-row">@include('backend.rodeo.surveys._question', ['i' => '__i__', 'q' => ['type' => 'scale', 'required' => false]])</template>

    <script>
    (function () {
        var bank = @json($bankJson), sets = @json($sets->mapWithKeys(fn ($s) => [$s->id => ['type' => $s->type, 'options' => $s->options]])),
            colors = @json($colors), names = @json($categories->pluck('name', 'id')), listed = ['scale', 'single', 'multi'];
        var box = document.getElementById('sv-rows'), next = {{ count($rows) }};

        function cards() { return [].slice.call(box.querySelectorAll('.sv-qcard')); }
        var types = @json(\App\Models\SurveyQuestion::TYPES);
        function esc(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
        function field(card, f) { return card.querySelector('[data-f=' + f + ']'); }
        // Picking a bank question fills in its wording, category, answer type, answers and help
        function pick(card) {
            var id = field(card, 'pick').value; if (id === 'own') return;
            var b = bank.filter(function (x) { return String(x.id) === id; })[0];
            ['bank', 'question', 'category', 'type', 'answer_set', 'help', 'options'].forEach(function (f) { field(card, f).value = ''; });
            if (b) { field(card, 'bank').value = b.id; field(card, 'question').value = b.question; field(card, 'category').value = b.category || ''; field(card, 'type').value = b.type; field(card, 'answer_set').value = b.set || ''; field(card, 'help').value = b.help || ''; }
        }
        function sync(card) {
            var cat = field(card, 'category').value, type = field(card, 'type').value, set = field(card, 'answer_set').value;
            var answers = set && sets[set] ? sets[set].options : (field(card, 'options').value ? field(card, 'options').value.split('\n') : []);
            card.style.setProperty('--dot', colors[cat] || '#D5D9DA');
            card.querySelector('[data-show=info]').innerHTML = field(card, 'question').value
                ? '<span class="sv-cat"><span class="sv-dot" style="--dot:' + (colors[cat] || '#96999d') + '"></span>' + esc(names[cat] || 'Uncategorized') + '</span> <span class="sv-type">' + esc(types[type] || type) + '</span>'
                  + (answers.length ? ' <span class="sv-opts">' + answers.map(function (o) { return '<span class="sv-opt">' + esc(o) + '</span>'; }).join('') + '</span>' : '')
                  + (field(card, 'help').value ? '<div class="muted" style="font-size:.8rem;margin-top:4px">' + esc(field(card, 'help').value) + '</div>' : '')
                : '<span class="muted">Pick a question from the Question Bank.</span>';
        }
        function refresh() {
            var list = cards();
            list.forEach(function (c, n) { c.querySelector('.num').textContent = 'Q' + (n + 1); sync(c); });
            document.getElementById('sv-empty').hidden = list.length > 0;
            document.getElementById('sv-count').textContent = list.length + (list.length === 1 ? ' question' : ' questions') + ' · ' + list.filter(function (c) { return c.querySelector('[data-f=required]').checked; }).length + ' required';
            // Outline: categories in the order they first appear, with their question numbers
            var groups = [], seen = {};
            list.forEach(function (c, n) {
                var id = c.querySelector('[data-f=category]').value || '0';
                if (!seen[id]) { seen[id] = { id: id, nums: [] }; groups.push(seen[id]); }
                seen[id].nums.push(n + 1);
            });
            document.getElementById('sv-outline').innerHTML = groups.map(function (g) {
                return '<li><span class="sv-dot" style="--dot:' + (colors[g.id] || '#96999d') + '"></span><span>' + (names[g.id] || 'Uncategorized') + '</span><span>Q' + g.nums.join(', Q') + '</span></li>';
            }).join('') || '<li class="muted">Nothing yet</li>';
        }
        function add(values) {
            var html = document.getElementById('sv-row').innerHTML.replace(/__i__/g, 'n' + (next++));
            box.insertAdjacentHTML('beforeend', html);
            var card = box.lastElementChild;
            Object.keys(values || {}).forEach(function (k) {
                var el = card.querySelector('[data-f=' + k + ']'); if (!el) return;
                if (el.type === 'checkbox') el.checked = !!values[k]; else el.value = values[k] == null ? '' : values[k];
            });
            refresh(); card.scrollIntoView({ block: 'center', behavior: 'smooth' });
            field(card, 'pick').focus({ preventScroll: true });
        }

        box.addEventListener('change', function (e) { var c = e.target.closest('.sv-qcard'); if (!c) return; if (e.target.matches('[data-f=pick]')) pick(c); refresh(); });
        box.addEventListener('click', function (e) {
            var b = e.target.closest('[data-move]'); if (!b) return;
            var c = b.closest('.sv-qcard');
            if (b.dataset.move === 'up' && c.previousElementSibling) box.insertBefore(c, c.previousElementSibling);
            if (b.dataset.move === 'down' && c.nextElementSibling) box.insertBefore(c.nextElementSibling, c);
            if (b.dataset.move === 'remove') c.remove();
            refresh();
        });
        document.getElementById('sv-add').addEventListener('click', function () { add({}); });
        // Every question must be picked before saving
        document.getElementById('sv-form').addEventListener('submit', function (e) {
            var empty = cards().filter(function (c) { return !field(c, 'question').value; })[0];
            if (empty) { e.preventDefault(); empty.scrollIntoView({ block: 'center' }); field(empty, 'pick').focus(); }
        });
        refresh();
    })();
    </script>
@endsection
