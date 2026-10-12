@extends('backend.layouts.app')

@section('crumb', 'Add Address')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Add to Suppression List', 'sub' => 'No email will go to this address until it is removed from the list.'])
    <form method="post" action="{{ route('rodeo.emails.suppressions.store') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" value="{{ old('email', $email) }}">
            <label for="reason">Reason</label><select id="reason" name="reason">@foreach (\App\Models\EmailSuppression::REASONS as $v => $l)<option value="{{ $v }}" @selected(old('reason', 'manual') === $v)>{{ $l }}</option>@endforeach</select>
            <label for="note">Note</label><input id="note" name="note" maxlength="250" value="{{ old('note') }}" placeholder="Optional, e.g. asked on the phone to stop emails">
        </div>
        <div class="panel-body actions"><button class="btn cyan">Add Address</button><a class="btn ghost" href="{{ route('rodeo.emails.suppressions') }}">Cancel</a></div>
    </form>
@endsection
