@extends('backend.layouts.app')

@section('crumb', $market->exists ? 'Edit Market' : 'Add a Market')

@section('content')
    @include('backend.partials.page-head', ['title' => $market->exists ? 'Edit Market: '.$market->name : 'Add a Market',
        'sub' => $market->exists ? $market->zip_ranges_count.' zip ranges · '.$market->fees_count.' TDSP fee records' : 'After adding a market, set its TDSP fees and rates so the website can price plans there.'])

    <form method="post" action="{{ $market->exists ? route('sheriff.markets.update', $market) : route('sheriff.markets.store') }}" class="panel"><div class="panel-body">
        @csrf @if ($market->exists) @method('put') @endif
        <div class="form-grid">
            <label for="name">Name</label><input id="name" name="name" required class="mono" value="{{ old('name', $market->name) }}" placeholder="TX-E-ONCOR">
            <label for="short">Short Name</label><input id="short" name="short" required value="{{ old('short', $market->short) }}">
            <label for="description">Description</label><input id="description" name="description" required value="{{ old('description', $market->description) }}">
            <label for="region">Region</label><input id="region" name="region" required value="{{ old('region', $market->region) }}" placeholder="Column in Astro's term discount grid">
            @foreach (['type' => ['Type', ['TDSP', 'Utility', 'Co-op', 'Municipal']], 'commodity' => ['Commodity', ['Electric', 'Gas']], 'units' => ['Units', ['kWh', 'Therms', 'CCF']], 'status' => ['Status', ['Active', 'Inactive']]] as $field => [$label, $options])
                <label for="{{ $field }}">{{ $label }}</label>
                <select id="{{ $field }}" name="{{ $field }}">@foreach ($options as $o)<option @selected(old($field, $market->$field) === $o)>{{ $o }}</option>@endforeach</select>
            @endforeach
            <label for="state">State</label><input id="state" name="state" required maxlength="2" value="{{ old('state', $market->state) }}" style="max-width:6ch">
            <label for="phone">Utility Phone</label><input id="phone" name="phone" value="{{ old('phone', $market->phone) }}">
            <label for="duns_number">DUNS Number</label><input id="duns_number" name="duns_number" class="mono" inputmode="numeric" value="{{ old('duns_number', $market->duns_number) }}">
            <label for="edi_name">EDI Name</label><input id="edi_name" name="edi_name" class="mono" value="{{ old('edi_name', $market->edi_name) }}">
        </div>
        <div class="actions" style="margin-top:20px"><button class="btn cyan">Save Market</button><a class="btn ghost" href="{{ route('sheriff.markets.index') }}">Cancel</a></div>
    </div></form>
@endsection
