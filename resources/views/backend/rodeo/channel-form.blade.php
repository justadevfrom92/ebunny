@extends('backend.layouts.app')

@section('crumb', $channel->exists ? 'Edit MSID '.$channel->msid : 'New MSID')

@section('content')
    @include('backend.partials.page-head', ['title' => $channel->exists ? 'Edit MSID '.$channel->msid : 'New MSID', 'sub' => 'A sales channel. Website links carry it as ?msid=.'])
    <form method="post" action="{{ route('rodeo.channels.save') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="msid">MSID</label>@if ($channel->exists)<div><input value="{{ $channel->msid }}" disabled style="max-width:12ch"><input type="hidden" name="msid" value="{{ $channel->msid }}"></div>@else<input id="msid" name="msid" required value="{{ old('msid') }}" inputmode="numeric" placeholder="3 to 8 digits" style="max-width:12ch">@endif
            <label for="name">Channel name</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $channel->name) }}">
            <label for="type">Type</label><select id="type" name="type">@foreach (['Organic', 'Paid', 'Partner', 'Broker', 'Agent'] as $t)<option @selected(old('type', $channel->type) === $t)>{{ $t }}</option>@endforeach</select>
            <span></span><label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $channel->active ?? true))> Active</label>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $channel->exists ? 'Save MSID' : 'Add MSID' }}</button><a class="btn ghost" href="{{ route('rodeo.channels') }}">Cancel</a></div>
    </form>
@endsection
