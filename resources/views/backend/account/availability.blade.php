@extends('backend.layouts.app')

@section('crumb', 'Availability')

{{-- Available, Busy or Out of Office: shown by your name to coworkers; while you're out Ranger won't assign you tickets --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Availability', 'sub' => 'You\'re '.$u->availabilityLabel().'.'])

    <form method="post" action="{{ route('admin.account.availability.save') }}" class="panel">@csrf @method('put')
        <div class="panel-body form-stack">
            <div class="field"><label>Status</label><div class="chips small" aria-label="Status">
                @foreach (\App\Models\User::AVAILABILITY as $k => [$label])
                    <label><input type="radio" name="availability" value="{{ $k }}" @checked(old('availability', $u->isOut() ? 'out' : ($u->availability === 'out' ? 'available' : $u->availability)) === $k) data-avail><span>{{ $label }}</span></label>
                @endforeach
            </div></div>
            <div class="field" data-out><label for="out_until">Back On</label><input id="out_until" name="out_until" type="date" min="{{ today()->toDateString() }}" value="{{ old('out_until', $u->out_until?->toDateString()) }}">
                @error('out_until')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
            <div class="field" data-out><label for="out_note">Note for Coworkers</label><input id="out_note" name="out_note" maxlength="250" value="{{ old('out_note', $u->out_note) }}" placeholder="e.g. On vacation; ask Taylor for anything urgent"></div>
            <p class="help" data-out>While you're out, Ranger won't let anyone assign you tickets. You're Available again on the day you're back.</p>
            <button class="btn cyan">Save</button>
        </div>
    </form>
    <script>(function () { var r = document.querySelectorAll('[data-avail]'), o = document.querySelectorAll('[data-out]');
        function show() { var out = document.querySelector('[data-avail]:checked'); out = out && out.value === 'out'; o.forEach(function (e) { e.hidden = !out; }); }
        r.forEach(function (x) { x.addEventListener('change', show); }); show(); })();</script>
@endsection
