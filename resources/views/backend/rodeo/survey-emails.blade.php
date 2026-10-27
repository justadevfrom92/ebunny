@extends('backend.layouts.app')

@section('crumb', 'Survey Emails')

{{-- The automatic survey emails: a 90-day check-in and a farewell survey, sent daily by the et:survey-emails cron. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Survey Emails', 'sub' => 'Sent automatically every day at 10am (et:survey-emails). Each customer gets each one once; suppressed emails are skipped.',
        'actions' => '<form method="post" action="'.route('rodeo.survey-emails.send').'" class="inline" data-confirm="Send the survey emails now?|Everyone due gets theirs now instead of at 10am.|Send Now">'.csrf_field().'<button class="btn cyan">Send Now</button></form>'])

    <div class="grid-2">
        @foreach (\App\Support\SurveyEmails::RULES as $k => [$name, $tpl, $slug, $who])
            @php $s = $stats[$k]; @endphp
            <div class="panel"><div class="panel-head"><h2>{{ $name }}</h2>@include('backend.partials.pill', ['text' => $s['due'] ? $s['due'].' due' : 'Up to date', 'tone' => $s['due'] ? 'warn' : 'ok'])</div>
                <div class="panel-body"><dl class="kv">
                    <dt>Who gets it</dt><dd>{{ $who }}</dd>
                    <dt>Email</dt><dd>@if ($s['template'])<a href="{{ route('rodeo.templates.edit', $s['template']) }}">{{ $s['template']->name }}</a><div class="help">“{{ $s['template']->subject }}”</div>@else<span class="muted">Template “{{ $tpl }}” is missing</span>@endif</dd>
                    <dt>Survey</dt><dd>@if ($s['survey'])<a href="{{ route('rodeo.surveys.edit', $s['survey']) }}">{{ $s['survey']->title }}</a> · <a href="{{ route('rodeo.surveys.results', $s['survey']) }}">Results</a>@else<span class="muted">Survey “{{ $slug }}” is missing</span>@endif</dd>
                </dl></div>
                <div class="grid-3 stat-row panel-body" style="border-top:1px solid var(--line)">
                    <div class="stat"><span>Sent</span><strong>{{ number_format($s['sent30']) }}</strong><small>last 30 days · {{ number_format($s['total']) }} in all</small></div>
                    <div class="stat"><span>Answered</span><strong>{{ number_format($s['answered']) }}</strong><small>customers who filled it in</small></div>
                    <div class="stat"><span>Response Rate</span><strong>{{ $s['rate'] === null ? '—' : $s['rate'].'%' }}</strong><small>of everyone sent one</small></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="panel"><div class="panel-head"><h2>Sent</h2><span class="muted">newest first</span></div><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Sent</th><th>Customer</th><th>Email</th><th>Status</th><th>Answered</th></tr></thead>
        <tbody>@forelse ($sent as $l)
            @php $done = isset($answered[$l->customer_id.'|'.($slugs[$l->template] ?? '')]); @endphp
            @php $u = route('rodeo.emails.sent.show', $l); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $l->id }}</b></a></td><td class="nowrap">{{ ($l->sent_at ?? $l->created_at)->format('n/j/Y g:i A') }}</td>
                <td>@if ($l->customer)@include('backend.partials.account-link', ['c' => $l->customer]) {{ $l->customer->name }}<div class="help">{{ $l->customer->email }} · {{ $l->customer->status }}</div>@else — @endif</td>
                <td>{{ $l->template === 'Survey - Farewell' ? 'Farewell Survey' : '90-Day Check-In' }}</td>
                <td>@include('backend.partials.pill', ['text' => ucfirst($l->status), 'tone' => ['sent' => 'ok', 'queued' => 'info', 'not sent' => 'warn'][$l->status] ?? ''])@if ($l->opened_at)<div class="help">opened {{ $l->opened_at->diffForHumans() }}</div>@endif</td>
                <td>{{ $done ? '✓ Yes' : '—' }}</td></tr>
        @empty <tr><td colspan="6" class="empty">None sent yet.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $sent])</div>
@endsection
