@extends('backend.layouts.app')

@section('crumb', $set->exists ? 'Edit '.$set->name : 'New Answer Set')

@section('content')
    @include('backend.partials.page-head', ['title' => $set->exists ? 'Edit Answer Set' : 'New Answer Set', 'sub' => 'A reusable list of answers. For a scale, list answers from lowest to highest.'])
    <form method="post" action="{{ $set->exists ? route('rodeo.surveys.answers.update', $set) : route('rodeo.surveys.answers.store') }}" class="panel simple-form">@csrf @if ($set->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="80" value="{{ old('name', $set->name) }}" placeholder="e.g. Satisfaction (5-point)">
            <label for="group">Kind</label><input id="group" name="group" required maxlength="40" list="sv-groups" value="{{ old('group', $set->group) }}" placeholder="Satisfaction, Agreement…">
            <datalist id="sv-groups">@foreach (\App\Models\SurveyAnswerSet::GROUPS as $g)<option value="{{ $g }}">@endforeach</datalist>
            <label for="type">Type</label><select id="type" name="type">@foreach (\App\Models\SurveyAnswerSet::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('type', $set->type) === $v)>{{ $l }}</option>@endforeach</select>
            <label for="options">Answers, one per line</label><textarea id="options" name="options" rows="6" required style="min-height:140px;font-family:inherit">{{ old('options', $set->exists ? implode("\n", $set->options) : '') }}</textarea>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $set->exists ? 'Save Answer Set' : 'Add Answer Set' }}</button><a class="btn ghost" href="{{ route('rodeo.surveys.answers') }}">Cancel</a></div>
    </form>
@endsection
