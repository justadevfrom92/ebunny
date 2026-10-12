@extends('backend.layouts.app')

@section('crumb', $promo->exists ? 'Edit '.$promo->code : 'New Promo Code')

@section('content')
    @include('backend.partials.page-head', ['title' => $promo->exists ? 'Edit Promo Code '.$promo->code : 'New Promo Code', 'sub' => 'A bill credit customers get with ?promo= on a sign-up link.'])
    <form method="post" action="{{ route('rodeo.promos.save') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="code">Code</label>@if ($promo->exists)<div><input value="{{ $promo->code }}" disabled style="max-width:16ch"><input type="hidden" name="code" value="{{ $promo->code }}"></div>@else<input id="code" name="code" required value="{{ old('code') }}" placeholder="e.g. FALL25" style="max-width:16ch;text-transform:uppercase">@endif
            <label for="description">Description</label><input id="description" name="description" required maxlength="150" value="{{ old('description', $promo->description) }}">
            <label for="credit">Bill credit ($)</label><input id="credit" name="credit" type="number" step="0.01" min="0" max="1000" value="{{ old('credit', $promo->credit) }}" style="max-width:12ch">
            <label for="starts_on">Starts</label><input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', $promo->starts_on?->toDateString()) }}" style="max-width:20ch">
            <label for="ends_on">Ends</label><input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $promo->ends_on?->toDateString()) }}" style="max-width:20ch">
            <span></span><label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $promo->active ?? true))> Active</label>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $promo->exists ? 'Save Promo Code' : 'Add Promo Code' }}</button><a class="btn ghost" href="{{ route('rodeo.channels') }}">Cancel</a></div>
    </form>
@endsection
