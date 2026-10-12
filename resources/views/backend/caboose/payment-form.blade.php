@extends('backend.layouts.app')

@section('crumb', 'Record a Payment')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Record a Payment', 'sub' => 'Checks, money orders, cash and wires received. The amount comes off the account balance.'])
    <form method="post" action="{{ route('caboose.payments.record') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
                <label for="r-account">Account #</label><input id="r-account" name="account" required value="{{ old('account') }}">
                <label for="r-amount">Amount ($)</label><input id="r-amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount') }}">
                <label for="r-kind">For</label><select id="r-kind" name="kind"><option>Balance Payment</option><option>Deposit</option></select>
                <label for="r-method">Method</label><select id="r-method" name="method">@foreach (['Check', 'Money Order', 'Cash', 'Wire'] as $mth)<option>{{ $mth }}</option>@endforeach</select>
                <label for="r-ref">Check # / Reference</label><input id="r-ref" name="reference" value="{{ old('reference') }}">
        </div>
        <div class="panel-body actions"><button class="btn cyan">Record Payment</button><a class="btn ghost" href="{{ route('caboose.payments') }}">Cancel</a></div>
    </form>
@endsection
