@extends('backend.layouts.app')

@section('crumb', 'Open a Case')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Open an HR Case', 'sub' => 'Confidential. Only people with Bunkhouse can see it.'])
    <form method="post" action="{{ route('bunkhouse.cases.store') }}" class="stack" style="max-width:980px">@csrf
        <div class="panel"><div class="panel-body stack">
            <div class="field"><label>About</label>@include('backend.bunkhouse._pick', ['name' => 'user_id', 'people' => $people, 'selected' => array_filter([$pick])])</div>
            <div class="field"><label>Type</label><div class="chips small">@foreach (\App\Models\ErmCase::TYPES as $t)<label><input type="radio" name="type" value="{{ $t }}" @checked(old('type', 'Concern') === $t)><span>{{ $t }}</span></label>@endforeach</div></div>
            <div class="field"><label>Priority</label><div class="chips small">@foreach (\App\Models\ErmCase::PRIORITIES as $p)<label><input type="radio" name="priority" value="{{ $p }}" @checked(old('priority', 'Medium') === $p)><span>{{ $p }}</span></label>@endforeach</div></div>
            <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" required maxlength="200" value="{{ old('subject') }}"></div>
            <div class="field"><label for="body">What happened</label><textarea id="body" name="body" rows="5" required>{{ old('body') }}</textarea></div>
            <div class="field"><label>Assign to</label>@include('backend.bunkhouse._pick', ['name' => 'assigned_to', 'people' => $handlers, 'selected' => [auth()->id()]])</div>
        </div></div>
        <div class="actions"><button class="btn cyan">Open Case</button><a class="btn ghost" href="{{ route('bunkhouse.cases') }}">Cancel</a></div>
    </form>
@endsection
