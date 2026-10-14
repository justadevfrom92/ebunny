@extends('backend.layouts.app')

@section('crumb', 'Schedule Reviews')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Schedule Reviews', 'sub' => 'Pick one or more people. Each review goes to their manager (or contact, for 3rd-party users).'])
    <form method="post" action="{{ route('bunkhouse.reviews.store') }}" class="stack" style="max-width:980px">@csrf
        <div class="panel"><div class="panel-body stack">
            <div class="field"><label>Kind</label><div class="chips small">@foreach (\App\Models\ErmReview::KINDS as $k)<label><input type="radio" name="kind" value="{{ $k }}" @checked(old('kind', 'Mid-year') === $k)><span>{{ $k }}</span></label>@endforeach</div></div>
            <div class="grid-2">
                <div class="field"><label for="period">Period</label><input id="period" name="period" required maxlength="30" value="{{ old('period', now()->year.' '.(now()->month > 6 ? 'H2' : 'H1')) }}"></div>
                <div class="field"><label for="due_on">Due</label><input id="due_on" name="due_on" type="date" required value="{{ old('due_on', today()->addDays(30)->toDateString()) }}"></div>
            </div>
            <div class="field"><label>People</label>@include('backend.bunkhouse._pick', ['name' => 'user_ids[]', 'people' => $people, 'multiple' => true, 'selected' => array_filter([$pick])])</div>
        </div></div>
        <div class="actions"><button class="btn cyan">Schedule</button><a class="btn ghost" href="{{ route('bunkhouse.reviews') }}">Cancel</a></div>
    </form>
@endsection
