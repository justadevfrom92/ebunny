@extends('backend.layouts.app')

@section('crumb', 'New Contact')

{{-- New Contact: log a call, email, text or chat that happened with a customer, and how it ended. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'New Contact', 'sub' => 'Log a call, email, text or chat with a customer, and how it ended. It goes on the account\'s contact history and notes.',
        'actions' => $c ? '<a class="btn ghost" href="'.route('corral.customers.show', $c).'">Back to account</a>' : null])

    <form method="post" action="{{ route('corral.contacts.store') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="ct-account">Account</label><div><input id="ct-account" name="account" required class="mono" value="{{ old('account', $c?->account) }}" style="max-width:20ch">
                @if ($c)<div class="help">{{ $c->name }} · {{ $c->phone }} · {{ $c->email }}</div>@endif</div>
            <label>Kind</label><div class="chips small">@foreach (config('corral.contact_channels') as $k => $l)<label><input type="radio" name="channel" value="{{ $k }}" @checked(old('channel', $channel) === $k)><span>{{ $l }}</span></label>@endforeach</div>
            <label>Direction</label><div class="chips small"><label><input type="radio" name="direction" value="in" @checked(old('direction', 'in') === 'in')><span>From the customer</span></label><label><input type="radio" name="direction" value="out" @checked(old('direction') === 'out')><span>To the customer</span></label></div>
            <label for="ct-issue">Issue</label><div><input id="ct-issue" name="issue" maxlength="60" value="{{ old('issue') }}" placeholder="e.g. Balance Inquiry">
                <div class="chips small" style="margin-top:6px">@foreach ($issues->take(12) as $i)<button type="button" class="chip-link" onclick="document.getElementById('ct-issue').value=this.textContent">{{ $i }}</button>@endforeach</div></div>
            <label for="ct-min">Minutes</label><div><input id="ct-min" name="minutes" type="number" min="0" max="600" value="{{ old('minutes') }}" style="max-width:10ch"><div class="help">Phone calls only.</div></div>
            <label for="ct-body">What happened</label><textarea id="ct-body" name="body" required maxlength="5000" style="min-height:120px">{{ old('body') }}</textarea>
            <label>Resolution</label><div class="chips small">@foreach (config('corral.resolutions') as $r => $tone)<label><input type="radio" name="resolution" value="{{ $r }}" required @checked(old('resolution', 'Resolved') === $r)><span>{{ $r }}</span></label>@endforeach</div>
        </div>
        <div class="panel-body actions"><button class="btn cyan">Save Contact</button><a class="btn ghost" href="{{ $c ? route('corral.customers.show', $c) : route('corral.calls') }}">Cancel</a></div>
    </form>
@endsection
