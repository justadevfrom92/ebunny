@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', [
        'title' => $renew ? 'Renew / Change Plan' : ($biz ? 'Create Order - Biz' : 'Create Order'),
        'sub' => $renew ? 'For account <span class="mono">'.e($renew->account).'</span>' : 'Enroll a new '.($biz ? 'business' : 'residential').' customer over the phone.',
    ])

    <form method="post" action="{{ route('corral.orders.store') }}" class="panel simple-form"><div class="panel-body">
        @csrf
        <input type="hidden" name="biz" value="{{ $biz ? 1 : 0 }}">
        @if ($renew)<input type="hidden" name="renew" value="{{ $renew->account }}">@endif
        <div class="form-grid">
            @if ($biz)<label for="business_name">Business Name</label><input id="business_name" name="business_name" value="{{ old('business_name', $renew?->name) }}" @required($biz && ! $renew)>@endif
            <label for="name">{{ $biz ? 'Contact Name' : 'Customer Name' }}</label><input id="name" name="name" required value="{{ old('name', $c?->name) }}">
            <label for="phone">Phone</label><input id="phone" name="phone" required value="{{ old('phone', $c?->phone) }}">
            <label for="email">Email</label><input id="email" name="email" type="email" required value="{{ old('email', $c?->email) }}">
            <label for="address">Service Address</label><input id="address" name="address" required value="{{ old('address', $c?->address) }}">
            <label for="city">City</label><input id="city" name="city" required value="{{ old('city', $c?->city) }}">
            <label for="zip">Zip</label><input id="zip" name="zip" required maxlength="5" inputmode="numeric" value="{{ old('zip', $c?->zip) }}">
            <label for="esiid">ESIID</label><input id="esiid" name="esiid" class="mono" value="{{ old('esiid', $c?->esiid) }}" placeholder="Leave blank to look up later">
            <label for="market_id">Market</label>
            <select id="market_id" name="market_id">@foreach ($markets as $m)<option value="{{ $m->id }}" @selected(old('market_id', $c?->market_id) == $m->id)>{{ $m->name }}</option>@endforeach</select>
            <label for="plan_id">Plan</label>
            <select id="plan_id" name="plan_id">@foreach ($plans as $p)<option value="{{ $p->id }}" @selected(old('plan_id', $c?->plan_id) == $p->id)>{{ $p->name }} — {{ $p->term }} mo ({{ $p->internal }})</option>@endforeach</select>
            <label for="enrollment_type">Enrollment Type</label>
            <select id="enrollment_type" name="enrollment_type">@foreach (($renew ? ['Renewal'] : ['Switch', 'Move-In', 'Self-Selected Switch']) as $t)<option @selected(old('enrollment_type') === $t)>{{ $t }}</option>@endforeach</select>
            <label for="start_date">Requested Start</label><input id="start_date" name="start_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}">
            <label>Options</label>
            <div class="actions">
                @foreach (['autopay' => 'AutoPay', 'paperless' => 'Paperless', 'peak_perks' => 'Peak Perks'] as $field => $label)
                    <input type="hidden" name="{{ $field }}" value="0">
                    <label class="check"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $c ? $c->$field : in_array($field, ['autopay', 'paperless'])))> {{ $label }}</label>
                @endforeach
            </div>
            <label for="source">Source</label>
            <select id="source" name="source">@foreach (['Phone', 'Website', 'Referral', 'Power to Choose'] as $s)<option @selected(old('source') === $s)>{{ $s }}</option>@endforeach</select>
        </div>
        <div class="actions" style="margin-top:20px"><button class="btn cyan">{{ $renew ? 'Change Plan' : 'Submit Order' }}</button><a class="btn ghost" href="{{ $renew ? route('corral.customers.show', $renew) : route('corral.customers.index') }}">Cancel</a></div>
    </div></form>
@endsection
