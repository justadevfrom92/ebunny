@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Sent Emails', 'sub' => number_format($emails->total()).' '.Str::plural('email', $emails->total()).' to customers: account emails from Corral, campaigns and tests.'])

    <div class="search-layout">
        <aside class="search-side">
            <form method="get" class="panel"><div class="panel-head"><h2>Search Emails</h2></div><div class="panel-body">
                <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] ?? '' }}"></div>
                <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] ?? '' }}"></div>
                <div class="field"><label for="account">Account Number</label><input id="account" name="account" value="{{ $f['account'] ?? '' }}" inputmode="numeric"></div>
                <div class="field"><label for="email">Email Address</label><input id="email" name="email" value="{{ $f['email'] ?? '' }}"></div>
                <div class="field"><label for="template">Template</label><select id="template" name="template"><option value="">Any</option>@foreach ($templateNames as $t)<option @selected(($f['template'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
                <div class="field"><label for="stage">Status</label><select id="stage" name="stage"><option value="">Any</option>@foreach (\App\Http\Controllers\Backend\Rodeo\EmailController::STAGES as $v => $l)<option value="{{ $v }}" @selected(($f['stage'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="campaign">Campaign</label><select id="campaign" name="campaign"><option value="">Any</option>@foreach ($campaigns as $c)<option value="{{ $c->id }}" @selected(($f['campaign'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="field"><label for="user">Sent By</label><select id="user" name="user"><option value="">Anyone</option><option value="system" @selected(($f['user'] ?? '') === 'system')>System</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(($f['user'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <button class="btn">Search</button>@if (array_filter($f))<a class="btn ghost" href="{{ route('rodeo.emails.sent') }}">Clear</a>@endif
            </div></form>
        </aside>
        <div class="search-main">
            <div class="panel"><div class="table-wrap"><table class="table">
                <thead><tr><th>Sent</th><th>Account</th><th>Template</th><th>Status</th></tr></thead>
                <tbody>@forelse ($emails as $e)
                    <tr class="click" data-href="{{ route('rodeo.emails.sent.show', $e) }}">
                        <td class="nowrap">{{ ($e->sent_at ?? $e->created_at)->format('n/j/Y g:i A') }}</td>
                        <td>@if ($e->customer)<a class="mono" href="{{ route('rodeo.emails.sent', ['account' => $e->customer->account]) }}" title="Every email to this account">{{ $e->customer->account }}</a><div class="muted">{{ $e->customer->name }}</div><div class="muted" style="font-size:.78rem">{{ $e->customer->email }}</div>@endif</td>
                        <td class="wrap"><a href="{{ route('rodeo.emails.sent.show', $e) }}">{{ $e->template }}</a><div class="muted">{{ $e->campaign ? 'Campaign: '.$e->campaign->name : ($e->user?->name ?? 'System') }}</div></td>
                        <td>@include('backend.rodeo.emails._stage', ['e' => $e])@if ($e->opened_at)<div class="muted" style="font-size:.78rem">opened {{ $e->opened_at->format('n/j g:i A') }}</div>@endif @if ($e->clicked_at)<div class="muted" style="font-size:.78rem">clicked {{ $e->clicked_at->format('n/j g:i A') }}</div>@endif</td></tr>
                @empty <tr><td colspan="4" class="empty">No emails match.</td></tr> @endforelse</tbody>
            </table></div>
            @include('backend.partials.pager', ['p' => $emails])</div>
        </div>
    </div>
@endsection
