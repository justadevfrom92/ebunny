@extends('backend.layouts.app')

@section('crumb', 'Request a Refund')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Request a Refund', 'sub' => 'A refund of a credit balance or deposit. Approving it needs the refunds right.'])
    <form method="post" action="{{ route('caboose.refunds.request') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
                <label for="f-account">Account #</label><input id="f-account" name="account" required value="{{ old('account', request('account')) }}">
                <label for="f-kind">Refund Of</label><select id="f-kind" name="kind"><option value="balance">Credit balance</option><option value="deposit">Deposit</option></select>
                <label for="f-amount">Amount ($)</label><input id="f-amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount', request('amount')) }}">
                <label for="f-method">Send By</label><select id="f-method" name="method"><option>Check</option><option>Card</option><option>ACH</option></select>
                <label for="f-reason">Reason</label><input id="f-reason" name="reason" required value="{{ old('reason') }}">
        </div>
        <div class="panel-body actions"><button class="btn cyan">Request Refund</button><a class="btn ghost" href="{{ route('caboose.refunds') }}">Cancel</a></div>
    </form>
@endsection
