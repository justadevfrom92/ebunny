@extends('backend.layouts.app')

@section('crumb', $question->exists ? 'Edit Question' : 'New Question')

@section('content')
    @include('backend.partials.page-head', ['title' => $question->exists ? 'Edit Question' : 'New Question', 'sub' => 'A ready-made question for the Question Bank.'])
    <form method="post" action="{{ $question->exists ? route('rodeo.surveys.bank.update', $question) : route('rodeo.surveys.bank.store') }}" class="panel simple-form">@csrf @if ($question->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="question">Question</label><textarea id="question" name="question" rows="3" required maxlength="250" style="min-height:70px;font-family:inherit">{{ old('question', $question->question) }}</textarea>
            <label for="cat">Category</label><select id="cat" name="survey_category_id"><option value="">Uncategorized</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('survey_category_id', $question->survey_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
            <label for="bk-type">Answer type</label><select id="bk-type" name="type">@foreach (\App\Models\SurveyQuestion::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('type', $question->type) === $v)>{{ $l }}</option>@endforeach</select>
            <label for="set" data-set>Answer set</label><select id="set" name="survey_answer_set_id" data-set><option value="">Choose…</option>@foreach ($sets->groupBy('group') as $g => $list)<optgroup label="{{ $g }}">@foreach ($list as $s)<option value="{{ $s->id }}" @selected(old('survey_answer_set_id', $question->survey_answer_set_id) == $s->id)>{{ $s->name }}</option>@endforeach</optgroup>@endforeach</select>
            <label for="help">Help text</label><input id="help" name="help" maxlength="200" value="{{ old('help', $question->help) }}" placeholder="Optional hint under the question">
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $question->exists ? 'Save Question' : 'Add to Bank' }}</button><a class="btn ghost" href="{{ route('rodeo.surveys.bank') }}">Cancel</a></div>
    </form>
    <script>
    (function () {
        var t = document.getElementById('bk-type');
        function sync() { [].slice.call(document.querySelectorAll('[data-set]')).forEach(function (el) { el.hidden = ['scale', 'single', 'multi'].indexOf(t.value) < 0; }); }
        t.addEventListener('change', sync); sync();
    })();
    </script>
@endsection
