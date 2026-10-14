@extends('backend.layouts.app')

@section('crumb', 'Request Time Off')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Request Time Off', 'sub' => 'Hours default to 8 for each weekday in the range.'])
    <form method="post" action="{{ route('bunkhouse.time-off.store') }}" class="stack" style="max-width:980px">@csrf
        <div class="panel"><div class="panel-body stack">
            <div class="field"><label>Who</label>@include('backend.bunkhouse._pick', ['name' => 'user_id', 'people' => $people, 'selected' => array_filter([$pick ?? auth()->id()])])</div>
            <div class="field"><label>Type</label><div class="chips small">@foreach (\App\Models\ErmTimeOff::TYPES as $t)<label><input type="radio" name="type" value="{{ $t }}" @checked(old('type', 'PTO') === $t)><span>{{ $t }}</span></label>@endforeach</div></div>
            <div class="grid-3">
                <div class="field"><label for="starts_on">First day</label><input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on') }}"></div>
                <div class="field"><label for="ends_on">Last day</label><input id="ends_on" name="ends_on" type="date" required value="{{ old('ends_on') }}"></div>
                <div class="field"><label for="hours">Hours</label><input id="hours" name="hours" type="number" step="0.5" min="1" value="{{ old('hours') }}" placeholder="8 a weekday"></div>
            </div>
            <div class="field"><label for="note">Note</label><input id="note" name="note" maxlength="250" value="{{ old('note') }}"></div>
        </div></div>
        <div class="actions"><button class="btn cyan">Request</button><a class="btn ghost" href="{{ route('bunkhouse.time-off') }}">Cancel</a></div>
    </form>
@endsection
