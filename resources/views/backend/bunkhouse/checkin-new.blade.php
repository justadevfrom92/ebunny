@extends('backend.layouts.app')

@section('crumb', 'Log a Check-in')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Log a Check-in', 'sub' => 'A one-on-one you had with someone. You\'re saved as the manager who held it.'])
    <form method="post" action="{{ route('bunkhouse.checkins.store') }}" class="stack" style="max-width:980px">@csrf
        <div class="panel"><div class="panel-body stack">
            <div class="field"><label>With</label>@include('backend.bunkhouse._pick', ['name' => 'user_id', 'people' => $people, 'selected' => array_filter([$pick])])</div>
            <div class="field"><label for="held_on">Date</label><input id="held_on" name="held_on" type="date" required value="{{ old('held_on', today()->toDateString()) }}" style="max-width:20ch"></div>
            <div class="field"><label>How they're feeling about work</label><div class="chips small">@foreach (\App\Models\ErmCheckin::MOODS as $n => $l)<label><input type="radio" name="mood" value="{{ $n }}" @checked((string) old('mood') === (string) $n)><span>{{ $l }}</span></label>@endforeach</div></div>
            <div class="field"><label for="topics">What you talked about</label><textarea id="topics" name="topics" rows="4" required>{{ old('topics') }}</textarea></div>
            <div class="field"><label for="action_items">Next steps</label><textarea id="action_items" name="action_items" rows="3">{{ old('action_items') }}</textarea></div>
        </div></div>
        <div class="actions"><button class="btn cyan">Save Check-in</button><a class="btn ghost" href="{{ route('bunkhouse.checkins') }}">Cancel</a></div>
    </form>
@endsection
