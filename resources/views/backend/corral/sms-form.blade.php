@extends('backend.layouts.app')

@section('crumb', 'Send a Text')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Send a Text', 'sub' => $c ? 'To '.e($c->name).' at <span class="mono">'.e($c->phone).'</span>' : 'Texts go to the phone number on the account.'])
    @unless ($ready)<div class="alert info" style="margin-bottom:16px"><b>Texts are logged, not sent.</b> Add the Twilio settings to .env (see Sheriff → APIs) to send texts.</div>@endunless
    @if ($recent->isNotEmpty())
        <div class="panel simple-form" style="margin-bottom:16px"><div class="panel-head"><h2>Last messages</h2></div><div class="table-wrap"><table><tbody>
            @foreach ($recent as $m)<tr><td class="nowrap">{{ $m->created_at->format('n/j g:i A') }}</td><td>{{ $m->direction === 'in' ? 'From customer' : 'To customer' }}</td><td class="wrap">{{ $m->body }}</td></tr>@endforeach
        </tbody></table></div></div>
    @endif
    <form method="post" action="{{ route('corral.sms.send') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="account">Account Number</label><input id="account" name="account" required value="{{ old('account', $c?->account) }}" inputmode="numeric" style="max-width:16ch">
            <label for="message">Message</label><textarea id="message" name="message" required maxlength="480" style="min-height:110px;font-family:inherit">{{ old('message') }}</textarea>
        </div>
        <div class="panel-body actions"><button class="btn cyan">Send Text</button><a class="btn ghost" href="{{ route('corral.sms') }}">Cancel</a></div>
    </form>
@endsection
